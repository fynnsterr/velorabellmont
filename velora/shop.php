<?php
require __DIR__ . '/includes/bootstrap.php';

$slug = trim((string) ($_GET['category'] ?? ''));
$q = trim((string) ($_GET['q'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'new');
$sorts = [
    'new' => ['Newest', 'p.id DESC'],
    'price_asc' => ['Price, low to high', 'p.price ASC'],
    'price_desc' => ['Price, high to low', 'p.price DESC'],
    'name' => ['Name, A to Z', 'p.name ASC'],
];
if (!isset($sorts[$sort])) {
    $sort = 'new';
}

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
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.description LIKE ? OR p.metal LIKE ?)';
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
    array_push($params, $like, $like, $like);
}

$sql = 'SELECT p.* FROM vb_products p WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $sorts[$sort][1] . ' LIMIT 100';
$st = db()->prepare($sql);
$st->execute($params);
$products = $st->fetchAll();

$pageTitle = $activeCat ? $activeCat['name'] : 'All jewelry';
require __DIR__ . '/includes/header.php';
?>
<section class="wrap section">
    <div class="page-head">
        <h1><?= e($pageTitle) ?></h1>
        <p class="muted"><?= count($products) ?> <?= count($products) === 1 ? 'piece' : 'pieces' ?></p>
    </div>

    <div class="toolbar">
        <div class="pill-row" role="navigation" aria-label="Categories">
            <a class="pill<?= !$activeCat ? ' is-active' : '' ?>" href="<?= e(url('shop.php' . ($q !== '' ? '?q=' . urlencode($q) : ''))) ?>">All</a>
            <?php foreach (categories() as $c): ?>
                <a class="pill<?= $activeCat && $activeCat['id'] === $c['id'] ? ' is-active' : '' ?>" href="<?= e(url('shop.php?category=' . $c['slug'] . ($q !== '' ? '&q=' . urlencode($q) : ''))) ?>"><?= e($c['name']) ?></a>
            <?php endforeach; ?>
        </div>
        <form class="filters" method="get" action="<?= e(url('shop.php')) ?>">
            <?php if ($activeCat): ?><input type="hidden" name="category" value="<?= e($activeCat['slug']) ?>"><?php endif; ?>
            <label class="sr-only" for="q">Search jewelry</label>
            <input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Search jewelry">
            <label class="sr-only" for="sort">Sort by</label>
            <select id="sort" name="sort" data-autosubmit>
                <?php foreach ($sorts as $key => $s): ?>
                    <option value="<?= e($key) ?>"<?= $sort === $key ? ' selected' : '' ?>><?= e($s[0]) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm">Apply</button>
        </form>
    </div>

    <?php if ($products): ?>
        <div class="grid-products">
            <?php foreach ($products as $p): require __DIR__ . '/includes/product_card.php'; endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty">
            <h2>Nothing matches that search</h2>
            <p>Try a different word, or clear the search to see every piece.</p>
            <p><a class="btn btn-primary" href="<?= e(url('shop.php')) ?>">Show all jewelry</a></p>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
