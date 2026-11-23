<?php
require __DIR__ . '/includes/bootstrap.php';

function shop_list_param(string $key, array $allowed): array
{
    $raw = $_GET[$key] ?? [];
    $out = [];
    foreach ((array) $raw as $v) {
        if (is_string($v) && in_array($v, $allowed, true) && !in_array($v, $out, true)) {
            $out[] = $v;
        }
    }
    return $out;
}

function shop_price_param(string $key): ?float
{
    $v = str_in($_GET, $key);
    if ($v === '' || !is_numeric($v) || (float) $v < 0 || (float) $v > 1000000) {
        return null;
    }
    return round((float) $v, 2);
}

$opts = cache_remember('shop_filter_options', function () {
    $finishes = db()->query("SELECT DISTINCT metal FROM vb_products WHERE active = 1 ORDER BY metal")->fetchAll(PDO::FETCH_COLUMN);
    $sizes = [];
    $stones = [];
    foreach (db()->query("SELECT sizes, stone_color FROM vb_products WHERE active = 1")->fetchAll() as $r) {
        foreach (explode(',', (string) $r['sizes']) as $sz) {
            $sz = trim($sz);
            if ($sz !== '') {
                $sizes[$sz] = true;
            }
        }
        $sc = trim(explode(',', (string) $r['stone_color'])[0]);
        if ($sc !== '') {
            $stones[$sc] = true;
        }
    }
    $sizeList = array_map("strval", array_keys($sizes));
    usort($sizeList, function ($a, $b) {
        return is_numeric($a) && is_numeric($b) ? (float) $a <=> (float) $b : strcmp((string) $a, (string) $b);
    });
    $stoneList = [];
    foreach (stone_colour_options() as $k => $label) {
        if (isset($stones[$k])) {
            $stoneList[$k] = $label;
        }
    }
    $bounds = db()->query("SELECT MIN(price) AS lo, MAX(price) AS hi FROM vb_products WHERE active = 1")->fetch();
    return ['finishes' => $finishes, 'sizes' => $sizeList, 'stones' => $stoneList, 'lo' => (float) ($bounds['lo'] ?? 0), 'hi' => (float) ($bounds['hi'] ?? 0)];
}, 300);

$slug = trim(str_in($_GET, 'category'));
$q = trim(mb_substr(str_in($_GET, 'q'), 0, 80));
$onlySale = isset($_GET['sale']);
$minP = shop_price_param('min');
$maxP = shop_price_param('max');
if ($minP !== null && $maxP !== null && $minP > $maxP) {
    [$minP, $maxP] = [$maxP, $minP];
}
$selFinish = shop_list_param('finish', $opts['finishes']);
$selStone = shop_list_param('stone', array_keys($opts['stones']));
$selSize = shop_list_param('size', $opts['sizes']);
$perPage = max(4, min(48, (int) cfg('shop.per_page', 12)));

$sorts = [
    'new' => ['Newest', 'p.id DESC'],
    'price_asc' => ['Price, low to high', 'EFF ASC'],
    'price_desc' => ['Price, high to low', 'EFF DESC'],
    'name' => ['Name, A to Z', 'p.name ASC'],
];
if ($q !== '') {
    $sorts = ['relevance' => ['Best match', ''], 'new' => $sorts['new'], 'price_asc' => $sorts['price_asc'], 'price_desc' => $sorts['price_desc'], 'name' => $sorts['name']];
}
$sort = str_in($_GET, 'sort', $q !== '' ? 'relevance' : 'new');
if (!isset($sorts[$sort])) {
    $sort = $q !== '' ? 'relevance' : 'new';
}

$eff = "(CASE WHEN p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price AND (p.sale_ends IS NULL OR p.sale_ends >= ?) THEN p.sale_price ELSE p.price END)";
$today = date('Y-m-d');
$where = ['p.active = 1'];
$params = [];
$activeCat = null;
foreach (categories() as $c) {
    if ($c['slug'] === $slug) {
        $activeCat = $c;
    }
}
if ($activeCat) {
    $where[] = 'p.category_id = ?';
    $params[] = (int) $activeCat['id'];
}
if ($onlySale) {
    $where[] = 'p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price AND (p.sale_ends IS NULL OR p.sale_ends >= ?)';
    $params[] = $today;
}
$found = null;
if ($q !== '') {
    $found = search_run($q, !isset($_GET['exact']));
    if ($found['ids']) {
        $where[] = 'p.id IN (' . implode(',', array_map('intval', $found['ids'])) . ')';
    } else {
        $where[] = '1 = 0';
    }
}
if ($minP !== null) {
    $where[] = $eff . ' >= CAST(? AS DECIMAL(10,2))';
    array_push($params, $today, $minP);
}
if ($maxP !== null) {
    $where[] = $eff . ' <= CAST(? AS DECIMAL(10,2))';
    array_push($params, $today, $maxP);
}
if ($selFinish) {
    $where[] = 'p.metal IN (' . implode(',', array_fill(0, count($selFinish), '?')) . ')';
    foreach ($selFinish as $f) {
        $params[] = $f;
    }
}
if ($selStone) {
    $parts = [];
    foreach ($selStone as $sc) {
        $parts[] = '(p.stone_color = ? OR p.stone_color LIKE ?)';
        $params[] = $sc;
        $params[] = $sc . ',%';
    }
    $where[] = '(' . implode(' OR ', $parts) . ')';
}
if ($selSize) {
    $parts = [];
    foreach ($selSize as $sz) {
        $esc = str_replace(['%', '_'], ['\\%', '\\_'], $sz);
        $parts[] = '(p.sizes = ? OR p.sizes LIKE ? OR p.sizes LIKE ? OR p.sizes LIKE ?)';
        array_push($params, $sz, $esc . ',%', '%,' . $esc . ',%', '%,' . $esc);
    }
    $where[] = '(' . implode(' OR ', $parts) . ')';
}
$whereSql = implode(' AND ', $where);

$cnt = db()->prepare('SELECT COUNT(*) FROM vb_products p WHERE ' . $whereSql);
$cnt->execute($params);
$total = (int) $cnt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min(max(1, (int) str_in($_GET, 'page', '1')), $pages);
$offset = ($page - 1) * $perPage;

if ($sort === 'relevance' && $found) {
    $st = db()->prepare('SELECT p.* FROM vb_products p WHERE ' . $whereSql);
    $st->execute($params);
    $all = $st->fetchAll();
    usort($all, function ($a, $b) use ($found) {
        return ($found['scores'][(int) $b['id']] ?? 0) <=> ($found['scores'][(int) $a['id']] ?? 0);
    });
    $products = array_slice($all, $offset, $perPage);
} else {
    $order = str_replace('EFF', $eff, $sorts[$sort][1]);
    $orderParams = strpos($sorts[$sort][1], 'EFF') !== false ? [$today] : [];
    $st = db()->prepare('SELECT p.* FROM vb_products p WHERE ' . $whereSql . ' ORDER BY ' . ($order !== '' ? $order : 'p.id DESC') . ', p.id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset);
    $st->execute(array_merge($params, $orderParams));
    $products = $st->fetchAll();
}

function shop_link(array $over = [], array $drop = []): string
{
    global $slug, $q, $sort, $onlySale, $minP, $maxP, $selFinish, $selStone, $selSize, $sorts;
    $p = [];
    if ($slug !== '') {
        $p['category'] = $slug;
    }
    if ($q !== '') {
        $p['q'] = $q;
    }
    $defaultSort = $q !== '' ? 'relevance' : 'new';
    if ($sort !== $defaultSort) {
        $p['sort'] = $sort;
    }
    if ($onlySale) {
        $p['sale'] = '1';
    }
    if ($minP !== null) {
        $p['min'] = rtrim(rtrim(number_format($minP, 2, '.', ''), '0'), '.');
    }
    if ($maxP !== null) {
        $p['max'] = rtrim(rtrim(number_format($maxP, 2, '.', ''), '0'), '.');
    }
    if ($selFinish) {
        $p['finish'] = $selFinish;
    }
    if ($selStone) {
        $p['stone'] = $selStone;
    }
    if ($selSize) {
        $p['size'] = $selSize;
    }
    foreach ($drop as $k) {
        unset($p[$k]);
    }
    foreach ($over as $k => $v) {
        if ($v === null) {
            unset($p[$k]);
        } else {
            $p[$k] = $v;
        }
    }
    $qs = http_build_query($p);
    return url('shop.php' . ($qs !== '' ? '?' . $qs : ''));
}

$chips = [];
if ($minP !== null || $maxP !== null) {
    $label = $minP !== null && $maxP !== null ? money($minP) . ' to ' . money($maxP) : ($minP !== null ? 'From ' . money($minP) : 'Up to ' . money($maxP));
    $chips[] = [$label, shop_link(['min' => null, 'max' => null, 'page' => null])];
}
foreach ($selFinish as $f) {
    $chips[] = [$f, shop_link(['finish' => array_values(array_diff($selFinish, [$f])) ?: null, 'page' => null])];
}
foreach ($selStone as $sc) {
    $chips[] = [$opts['stones'][$sc] . ' stone', shop_link(['stone' => array_values(array_diff($selStone, [$sc])) ?: null, 'page' => null])];
}
foreach ($selSize as $sz) {
    $chips[] = ['Size ' . $sz, shop_link(['size' => array_values(array_diff($selSize, [$sz])) ?: null, 'page' => null])];
}
$filterCount = count($chips);

$pageTitle = $onlySale ? 'Offers' : ($activeCat ? $activeCat['name'] : 'All jewelry');
if ($q !== '') {
    $pageTitle = 'Search: ' . $q;
}
$hasSale = count(sale_products(1)) > 0;
$pageScripts = ['js/search.js'];
require __DIR__ . '/includes/header.php';
$from = $total ? $offset + 1 : 0;
$to = min($total, $offset + $perPage);
?>
<section class="wrap section">
    <div class="page-head">
        <h1><?= e($q !== '' ? 'Search results' : $pageTitle) ?></h1>
        <p class="muted" id="result-count"><?= $total === 0 ? '0 pieces' : ($pages > 1 ? 'Showing ' . $from . ' to ' . $to . ' of ' . $total . ' pieces' : $total . ' ' . ($total === 1 ? 'piece' : 'pieces')) ?></p>
    </div>

    <div class="toolbar">
        <div class="pill-row" role="navigation" aria-label="Categories">
            <a class="pill<?= !$activeCat && !$onlySale ? ' is-active' : '' ?>" href="<?= e(shop_link(['category' => null, 'sale' => null, 'page' => null])) ?>">All</a>
            <?php foreach (categories() as $c): ?>
                <a class="pill<?= $activeCat && $activeCat['id'] === $c['id'] ? ' is-active' : '' ?>" href="<?= e(shop_link(['category' => $c['slug'], 'sale' => null, 'page' => null])) ?>"><?= e($c['name']) ?></a>
            <?php endforeach; ?>
            <?php if ($hasSale): ?><a class="pill<?= $onlySale ? ' is-active' : '' ?>" href="<?= e(shop_link(['sale' => '1', 'category' => null, 'page' => null])) ?>">Offers</a><?php endif; ?>
        </div>
    </div>

    <form class="shop-filters" method="get" action="<?= e(url('shop.php')) ?>" role="search" aria-label="Search and filter jewelry">
        <?php if ($activeCat): ?><input type="hidden" name="category" value="<?= e($activeCat['slug']) ?>"><?php endif; ?>
        <?php if ($onlySale): ?><input type="hidden" name="sale" value="1"><?php endif; ?>
        <div class="filter-top">
            <div class="search-box">
                <label class="sr-only" for="q">Search jewelry</label>
                <input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Search, for example gold earrings or wedding" autocomplete="off" maxlength="80" data-suggest="<?= e(url('suggest.php')) ?>" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="suggest-list">
                <ul id="suggest-list" class="suggest-list" role="listbox" hidden></ul>
            </div>
            <label class="sr-only" for="sort">Sort by</label>
            <select id="sort" name="sort" data-autosubmit>
                <?php foreach ($sorts as $key => $s): ?>
                    <option value="<?= e($key) ?>"<?= $sort === $key ? ' selected' : '' ?>><?= e($s[0]) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-primary">Search</button>
        </div>
        <details class="filter-panel"<?= $filterCount ? ' open' : '' ?>>
            <summary>Filters<?= $filterCount ? ' (' . $filterCount . ')' : '' ?></summary>
            <div class="filter-grid">
                <fieldset>
                    <legend>Price (GH₵)</legend>
                    <div class="price-range">
                        <label class="sr-only" for="min">Lowest price</label>
                        <input id="min" type="number" name="min" min="0" step="1" inputmode="numeric" placeholder="<?= (int) floor($opts['lo']) ?>" value="<?= $minP !== null ? e((string) $minP) : '' ?>">
                        <span aria-hidden="true">to</span>
                        <label class="sr-only" for="max">Highest price</label>
                        <input id="max" type="number" name="max" min="0" step="1" inputmode="numeric" placeholder="<?= (int) ceil($opts['hi']) ?>" value="<?= $maxP !== null ? e((string) $maxP) : '' ?>">
                    </div>
                </fieldset>
                <?php if ($opts['finishes']): ?>
                <fieldset>
                    <legend>Finish</legend>
                    <?php foreach ($opts['finishes'] as $i => $f): ?>
                        <label class="check"><input type="checkbox" name="finish[]" value="<?= e($f) ?>"<?= in_array($f, $selFinish, true) ? ' checked' : '' ?>> <?= e($f) ?></label>
                    <?php endforeach; ?>
                </fieldset>
                <?php endif; ?>
                <?php if ($opts['stones']): ?>
                <fieldset>
                    <legend>Main stone colour</legend>
                    <?php foreach ($opts['stones'] as $sk => $sl): ?>
                        <label class="check"><input type="checkbox" name="stone[]" value="<?= e($sk) ?>"<?= in_array($sk, $selStone, true) ? ' checked' : '' ?>> <?= e($sl) ?></label>
                    <?php endforeach; ?>
                </fieldset>
                <?php endif; ?>
                <?php if ($opts['sizes']): ?>
                <fieldset>
                    <legend>Ring size</legend>
                    <div class="size-checks">
                    <?php foreach ($opts['sizes'] as $sz): ?>
                        <label class="check"><input type="checkbox" name="size[]" value="<?= e($sz) ?>"<?= in_array($sz, $selSize, true) ? ' checked' : '' ?>> <?= e($sz) ?></label>
                    <?php endforeach; ?>
                    </div>
                </fieldset>
                <?php endif; ?>
            </div>
            <p class="filter-actions"><button type="submit" class="btn btn-sm btn-primary">Show results</button> <a class="text-link" href="<?= e(url('shop.php' . ($activeCat ? '?category=' . $activeCat['slug'] : ''))) ?>">Clear everything</a></p>
        </details>
    </form>

    <?php if ($found && $found['corrected'] !== null): ?>
        <p class="search-note" role="status">Showing results for <strong><?= e($found['corrected']) ?></strong>. <a href="<?= e(shop_link(['exact' => '1', 'page' => null])) ?>">Search only for "<?= e($q) ?>"</a></p>
    <?php elseif ($found && $found['relaxed'] && $total): ?>
        <p class="search-note" role="status">Nothing matched every word, so these match some of them.</p>
    <?php endif; ?>

    <?php if ($chips): ?>
        <p class="chip-row" aria-label="Active filters">
            <?php foreach ($chips as [$label, $href]): ?>
                <a class="chip" href="<?= e($href) ?>"><?= e($label) ?> <span aria-hidden="true">&times;</span><span class="sr-only">remove filter</span></a>
            <?php endforeach; ?>
            <a class="text-link" href="<?= e(shop_link([], ['min', 'max', 'finish', 'stone', 'size', 'page'])) ?>">Clear filters</a>
        </p>
    <?php endif; ?>

    <?php if ($products): ?>
        <div class="grid-products">
            <?php $cardHeading = 'h2'; foreach ($products as $p): require __DIR__ . '/includes/product_card.php'; endforeach; ?>
        </div>
        <?php if ($pages > 1): ?>
            <nav class="pager" aria-label="Pages">
                <?php if ($page > 1): ?><a class="pill" rel="prev" href="<?= e(shop_link(['page' => $page > 2 ? $page - 1 : null])) ?>">Previous</a><?php endif; ?>
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <?php if ($i === 1 || $i === $pages || abs($i - $page) <= 1): ?>
                        <a class="pill<?= $i === $page ? ' is-active' : '' ?>" href="<?= e(shop_link(['page' => $i > 1 ? $i : null])) ?>"<?= $i === $page ? ' aria-current="page"' : '' ?> aria-label="Page <?= $i ?>"><?= $i ?></a>
                    <?php elseif (abs($i - $page) === 2): ?>
                        <span class="pager-gap" aria-hidden="true">&hellip;</span>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $pages): ?><a class="pill" rel="next" href="<?= e(shop_link(['page' => $page + 1])) ?>">Next</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty">
            <h2>Nothing matches that</h2>
            <p><?= $filterCount ? 'Try removing a filter, or widen the price range.' : 'Try a different word, such as a piece type (ring, earrings), a colour or an occasion.' ?></p>
            <p><a class="btn btn-primary" href="<?= e(url('shop.php')) ?>">Show all jewelry</a></p>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
