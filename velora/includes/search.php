<?php

function search_fold(string $s): string
{
    $s = mb_strtolower($s, 'UTF-8');
    $map = ['à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n'];
    return strtr($s, $map);
}

function search_stem(string $w): string
{
    if (strlen($w) > 4 && substr($w, -3) === 'ies') {
        return substr($w, 0, -3) . 'y';
    }
    if (strlen($w) > 4 && substr($w, -2) === 'es' && preg_match('/(ch|sh|x|z|ss)es$/', $w)) {
        return substr($w, 0, -2);
    }
    if (strlen($w) > 3 && substr($w, -1) === 's' && substr($w, -2) !== 'ss') {
        return substr($w, 0, -1);
    }
    return $w;
}

function search_tokens(string $text, bool $stem = true): array
{
    $stop = ['a', 'an', 'the', 'for', 'and', 'with', 'of', 'in', 'on', 'to', 'my', 'some', 'good', 'nice', 'best'];
    $out = [];
    foreach (preg_split('/[^a-z0-9]+/', search_fold($text), -1, PREG_SPLIT_NO_EMPTY) as $w) {
        if (in_array($w, $stop, true) || strlen($w) < 2) {
            continue;
        }
        $out[] = $stem ? search_stem($w) : $w;
    }
    return $out;
}

function search_synonyms(): array
{
    return [
        'wedding' => ['bridal', 'bride', 'pearl', 'church', 'formal'],
        'bridal' => ['wedding', 'bride', 'pearl'],
        'bride' => ['wedding', 'bridal', 'pearl'],
        'engagement' => ['ring', 'solitaire', 'wedding'],
        'church' => ['wedding', 'pearl', 'formal'],
        'graduation' => ['graduate', 'celebration'],
        'party' => ['evening', 'cocktail', 'gala', 'dinner'],
        'evening' => ['party', 'cocktail', 'gala', 'dinner'],
        'night' => ['evening', 'party', 'cocktail'],
        'dinner' => ['evening', 'party', 'gala'],
        'office' => ['work', 'everyday', 'classic'],
        'work' => ['office', 'everyday'],
        'daily' => ['everyday', 'layering'],
        'everyday' => ['daily', 'layering', 'wear'],
        'casual' => ['everyday', 'daily'],
        'necklace' => ['pendant', 'chain', 'collar', 'strand'],
        'chain' => ['necklace', 'pendant'],
        'pendant' => ['necklace', 'chain'],
        'collar' => ['necklace'],
        'earring' => ['stud', 'hoop', 'drop'],
        'stud' => ['earring'],
        'hoop' => ['earring'],
        'drop' => ['earring', 'teardrop'],
        'bracelet' => ['bangle', 'cuff', 'wrist'],
        'bangle' => ['bracelet', 'cuff'],
        'cuff' => ['bracelet', 'bangle'],
        'ring' => ['signet', 'solitaire', 'band'],
        'band' => ['ring'],
        'signet' => ['ring'],
        'solitaire' => ['ring', 'engagement'],
        'golden' => ['gold'],
        'silvery' => ['silver'],
        'diamond' => ['clear', 'crystal'],
        'crystal' => ['clear', 'stone'],
        'stone' => ['crystal'],
        'pearls' => ['pearl'],
        'gift' => ['present'],
        'present' => ['gift'],
        'minimal' => ['slim', 'delicate', 'simple'],
        'simple' => ['slim', 'delicate', 'minimal'],
        'statement' => ['bold', 'oversized', 'cocktail', 'wide'],
        'bold' => ['statement', 'oversized'],
        'layering' => ['layered', 'chain'],
    ];
}

function search_index(): array
{
    return cache_remember('search_index_v2', function () {
        $rows = db()->query("SELECT p.id, p.name, p.slug, p.description, p.metal, p.specs, p.stone_color, c.name AS cat FROM vb_products p JOIN vb_categories c ON c.id = p.category_id WHERE p.active = 1")->fetchAll();
        $docs = [];
        $vocab = [];
        $colourWords = ['clear' => 'clear white diamond crystal', 'blue' => 'blue sapphire', 'green' => 'green emerald', 'red' => 'red ruby', 'black' => 'black onyx', 'pink' => 'pink', 'purple' => 'purple amethyst', 'pearl' => 'pearl pearls'];
        foreach ($rows as $r) {
            $stone = '';
            foreach (explode(',', (string) $r['stone_color']) as $sc) {
                $stone .= ' ' . ($colourWords[trim($sc)] ?? '');
            }
            $fields = [
                'name' => [5.0, $r['name']],
                'cat' => [4.0, $r['cat']],
                'finish' => [3.0, $r['metal']],
                'stone' => [3.0, $stone],
                'specs' => [2.0, str_replace('|', ' ', (string) $r['specs'])],
                'desc' => [1.0, $r['description']],
            ];
            $doc = ['id' => (int) $r['id'], 'fields' => []];
            foreach ($fields as $k => [$w, $text]) {
                $toks = array_values(array_unique(search_tokens((string) $text)));
                $doc['fields'][$k] = ['w' => $w, 't' => $toks];
                foreach ($toks as $t) {
                    $vocab[$t] = true;
                }
            }
            $docs[] = $doc;
        }
        return ['docs' => $docs, 'vocab' => array_keys($vocab)];
    }, 600);
}

function search_typo_limit(string $w): int
{
    $n = strlen($w);
    return $n <= 3 ? 0 : ($n <= 6 ? 1 : 2);
}

function search_match_word(string $q, string $w): float
{
    if ($q === $w) {
        return 1.0;
    }
    if (strlen($q) >= 3 && strpos($w, $q) === 0) {
        return 0.8;
    }
    if (strlen($q) >= 4 && strlen($w) >= 5 && strpos($q, $w) === 0) {
        return 0.5;
    }
    $lim = search_typo_limit($q);
    if ($lim > 0 && abs(strlen($q) - strlen($w)) <= $lim && levenshtein($q, $w) <= $lim) {
        return 0.6;
    }
    return 0.0;
}

function search_term_score(string $term, array $doc, array $syn): float
{
    $best = 0.0;
    foreach ($doc['fields'] as $f) {
        foreach ($f['t'] as $w) {
            $m = search_match_word($term, $w);
            if ($m > 0) {
                $best = max($best, $m * $f['w']);
            }
            foreach ($syn as $s) {
                if ($s === $w) {
                    $best = max($best, 0.35 * $f['w']);
                }
            }
        }
    }
    return $best;
}

function search_best_correction(string $term, array $vocab): ?string
{
    $lim = search_typo_limit($term);
    if ($lim === 0) {
        return null;
    }
    $best = null;
    $bd = 99;
    foreach ($vocab as $w) {
        if (abs(strlen($term) - strlen($w)) > $lim || strlen($w) < 4) {
            continue;
        }
        $d = levenshtein($term, $w);
        if ($d <= $lim && $d < $bd) {
            $bd = $d;
            $best = $w;
        }
    }
    return $best;
}

function search_run(string $query, bool $correct = true): array
{
    $terms = search_tokens($query);
    $terms = array_slice($terms, 0, 6);
    if (!$terms) {
        return ['ids' => [], 'scores' => [], 'relaxed' => false, 'corrected' => null, 'terms' => []];
    }
    $idx = search_index();
    $synMap = search_synonyms();
    $vocabSet = array_flip($idx['vocab']);
    $corrected = [];
    $changed = false;
    foreach ($terms as $t) {
        $known = isset($vocabSet[$t]);
        if (!$known && $correct) {
            $prefix = false;
            foreach ($idx['vocab'] as $w) {
                if (strlen($t) >= 3 && strpos($w, $t) === 0) {
                    $prefix = true;
                    break;
                }
            }
            $syn = $synMap[$t] ?? [];
            if (!$prefix && !$syn) {
                $fix = search_best_correction($t, $idx['vocab']);
                if ($fix !== null) {
                    $corrected[] = $fix;
                    $changed = true;
                    continue;
                }
            }
        }
        $corrected[] = $t;
    }
    $run = function (array $use, bool $all) use ($idx, $synMap) {
        $scores = [];
        foreach ($idx['docs'] as $doc) {
            $total = 0.0;
            $hits = 0;
            foreach ($use as $t) {
                $s = search_term_score($t, $doc, $synMap[$t] ?? []);
                if ($s > 0) {
                    $hits++;
                    $total += $s;
                }
            }
            if ($total > 0 && (!$all || $hits === count($use))) {
                $scores[$doc['id']] = $total + $hits * 0.01;
            }
        }
        arsort($scores);
        return $scores;
    };
    $scores = $run($corrected, true);
    $relaxed = false;
    if (!$scores && count($corrected) > 1) {
        $scores = $run($corrected, false);
        $relaxed = true;
    }
    return ['ids' => array_keys($scores), 'scores' => $scores, 'relaxed' => $relaxed, 'corrected' => $changed ? implode(' ', $corrected) : null, 'terms' => $corrected];
}

function search_suggest(string $query, int $limit = 6): array
{
    $query = trim($query);
    if (mb_strlen($query) < 2) {
        return ['products' => [], 'categories' => [], 'corrected' => null];
    }
    $r = search_run($query);
    $ids = array_slice($r['ids'], 0, $limit);
    $products = [];
    if ($ids) {
        $in = implode(',', array_map('intval', $ids));
        $rows = db()->query("SELECT p.*, c.name AS cat_name FROM vb_products p JOIN vb_categories c ON c.id = p.category_id WHERE p.id IN ($in)")->fetchAll();
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['id']] = $row;
        }
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $p = $byId[$id];
                $products[] = ['name' => $p['name'], 'url' => url('product.php?slug=' . urlencode($p['slug'])), 'price' => money(price_now($p)), 'category' => $p['cat_name'], 'image' => asset(image_variants($p['image']) ? array_values(image_variants($p['image']))[0] : $p['image'])];
            }
        }
    }
    $cats = [];
    $qt = $r['terms'];
    foreach (categories() as $c) {
        $ct = search_tokens($c['name']);
        foreach ($qt as $t) {
            foreach ($ct as $w) {
                if (search_match_word($t, $w) >= 0.8 || in_array($w, search_synonyms()[$t] ?? [], true)) {
                    $cats[$c['slug']] = ['name' => $c['name'], 'url' => url('shop.php?category=' . $c['slug'])];
                }
            }
        }
    }
    return ['products' => $products, 'categories' => array_values(array_slice($cats, 0, 3)), 'corrected' => $r['corrected']];
}
