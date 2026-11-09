<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$id = (int) val('id');
$product = null;
if ($id > 0) {
    $st = db()->prepare('SELECT * FROM vb_products WHERE id = ?');
    $st->execute([$id]);
    $product = $st->fetch();
    if (!$product) {
        flash('error', 'That product does not exist.');
        redirect('admin/products.php');
    }
}

function slugify(string $text): string
{
    $text = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text), '-'));
    return $text !== '' ? $text : 'product';
}

$errors = [];
$d = [
    'name' => $product['name'] ?? '',
    'category_id' => (string) ($product['category_id'] ?? ''),
    'price' => isset($product['price']) ? number_format((float) $product['price'], 2, '.', '') : '',
    'stock' => (string) ($product['stock'] ?? '0'),
    'metal' => $product['metal'] ?? 'Gold tone',
    'description' => $product['description'] ?? '',
    'featured' => (int) ($product['featured'] ?? 0),
    'active' => (int) ($product['active'] ?? 1),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $d = [
        'name' => trim(val('name')),
        'category_id' => val('category_id'),
        'price' => trim(val('price')),
        'stock' => trim(val('stock')),
        'metal' => trim(val('metal')),
        'description' => trim(val('description')),
        'featured' => isset($_POST['featured']) ? 1 : 0,
        'active' => isset($_POST['active']) ? 1 : 0,
    ];
    $catIds = array_map(function ($c) {
        return (string) $c['id'];
    }, categories());

    if (mb_strlen($d['name']) < 2 || mb_strlen($d['name']) > 160) {
        $errors['name'] = 'Enter a product name.';
    }
    if (!in_array($d['category_id'], $catIds, true)) {
        $errors['category_id'] = 'Choose a category.';
    }
    if (!is_numeric($d['price']) || (float) $d['price'] <= 0 || (float) $d['price'] > 100000) {
        $errors['price'] = 'Enter a price greater than 0.';
    }
    if (!ctype_digit($d['stock']) || (int) $d['stock'] > 100000) {
        $errors['stock'] = 'Enter the stock as a whole number.';
    }
    if ($d['metal'] === '' || mb_strlen($d['metal']) > 40) {
        $errors['metal'] = 'Enter the metal or finish, for example Gold tone.';
    }
    if (mb_strlen($d['description']) < 10) {
        $errors['description'] = 'Write a short description for shoppers.';
    }

    $imagePath = $product['image'] ?? '';
    $upload = $_FILES['image'] ?? null;
    if ($upload && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($upload['error'] !== UPLOAD_ERR_OK) {
            $errors['image'] = 'The image did not upload. Try a smaller file.';
        } elseif ($upload['size'] > 3 * 1024 * 1024) {
            $errors['image'] = 'Use an image under 3 MB.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($upload['tmp_name']);
            $exts = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($exts[$mime])) {
                $errors['image'] = 'Upload a JPG, PNG or WebP image.';
            } else {
                $name = bin2hex(random_bytes(8)) . '.' . $exts[$mime];
                $dir = VB_ROOT . '/assets/uploads';
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                if (!move_uploaded_file($upload['tmp_name'], $dir . '/' . $name)) {
                    $errors['image'] = 'We could not save the image. Check the uploads folder is writable.';
                } else {
                    $imagePath = 'uploads/' . $name;
                }
            }
        }
    }
    if ($imagePath === '' && !isset($errors['image'])) {
        $errors['image'] = 'Add a product image.';
    }

    if (!$errors) {
        if ($product) {
            $up = db()->prepare('UPDATE vb_products SET category_id = ?, name = ?, description = ?, price = ?, stock = ?, metal = ?, image = ?, featured = ?, active = ? WHERE id = ?');
            $up->execute([(int) $d['category_id'], $d['name'], $d['description'], (float) $d['price'], (int) $d['stock'], $d['metal'], $imagePath, $d['featured'], $d['active'], (int) $product['id']]);
            flash('success', $d['name'] . ' saved.');
        } else {
            $slug = slugify($d['name']);
            $chk = db()->prepare('SELECT COUNT(*) FROM vb_products WHERE slug = ?');
            $base = $slug;
            $i = 2;
            while (true) {
                $chk->execute([$slug]);
                if ((int) $chk->fetchColumn() === 0) {
                    break;
                }
                $slug = $base . '-' . $i++;
            }
            $ins = db()->prepare('INSERT INTO vb_products (category_id, name, slug, description, price, stock, metal, image, featured, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ins->execute([(int) $d['category_id'], $d['name'], $slug, $d['description'], (float) $d['price'], (int) $d['stock'], $d['metal'], $imagePath, $d['featured'], $d['active']]);
            flash('success', $d['name'] . ' added.');
        }
        redirect('admin/products.php');
    }
}

$pageTitle = $product ? 'Edit ' . $product['name'] : 'Add product';
require __DIR__ . '/_header.php';
?>
<div class="page-head"><h1><?= $product ? 'Edit product' : 'Add product' ?></h1></div>
<?php if ($errors): ?><p class="flash flash-error" role="alert">Fix the highlighted fields and save again.</p><?php endif; ?>

<form method="post" action="<?= e(url('admin/product_edit.php' . ($product ? '?id=' . (int) $product['id'] : ''))) ?>" enctype="multipart/form-data" class="form form-wide" novalidate>
    <?= csrf_field() ?>
    <div class="form-grid">
        <div class="field span-2<?= isset($errors['name']) ? ' has-error' : '' ?>">
            <label for="name">Name</label>
            <input id="name" type="text" name="name" value="<?= e($d['name']) ?>" required>
            <?php if (isset($errors['name'])): ?><p class="field-error"><?= e($errors['name']) ?></p><?php endif; ?>
        </div>
        <div class="field<?= isset($errors['category_id']) ? ' has-error' : '' ?>">
            <label for="category_id">Category</label>
            <select id="category_id" name="category_id" required>
                <option value="">Choose a category</option>
                <?php foreach (categories() as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"<?= $d['category_id'] === (string) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['category_id'])): ?><p class="field-error"><?= e($errors['category_id']) ?></p><?php endif; ?>
        </div>
        <div class="field<?= isset($errors['metal']) ? ' has-error' : '' ?>">
            <label for="metal">Metal or finish</label>
            <input id="metal" type="text" name="metal" value="<?= e($d['metal']) ?>" required>
            <?php if (isset($errors['metal'])): ?><p class="field-error"><?= e($errors['metal']) ?></p><?php endif; ?>
        </div>
        <div class="field<?= isset($errors['price']) ? ' has-error' : '' ?>">
            <label for="price">Price (GH₵)</label>
            <input id="price" type="text" inputmode="decimal" name="price" value="<?= e($d['price']) ?>" required>
            <?php if (isset($errors['price'])): ?><p class="field-error"><?= e($errors['price']) ?></p><?php endif; ?>
        </div>
        <div class="field<?= isset($errors['stock']) ? ' has-error' : '' ?>">
            <label for="stock">Stock</label>
            <input id="stock" type="text" inputmode="numeric" name="stock" value="<?= e($d['stock']) ?>" required>
            <?php if (isset($errors['stock'])): ?><p class="field-error"><?= e($errors['stock']) ?></p><?php endif; ?>
        </div>
        <div class="field span-2<?= isset($errors['description']) ? ' has-error' : '' ?>">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="5" required><?= e($d['description']) ?></textarea>
            <?php if (isset($errors['description'])): ?><p class="field-error"><?= e($errors['description']) ?></p><?php endif; ?>
        </div>
        <div class="field span-2<?= isset($errors['image']) ? ' has-error' : '' ?>">
            <label for="image">Image (JPG, PNG or WebP, up to 3 MB)</label>
            <?php if ($product): ?><img class="thumb" src="<?= e(asset($product['image'])) ?>" alt="" width="800" height="800"><?php endif; ?>
            <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp">
            <?php if (isset($errors['image'])): ?><p class="field-error"><?= e($errors['image']) ?></p><?php endif; ?>
        </div>
        <div class="field span-2 checks">
            <label class="check"><input type="checkbox" name="active" value="1"<?= $d['active'] ? ' checked' : '' ?>> Show in the shop</label>
            <label class="check"><input type="checkbox" name="featured" value="1"<?= $d['featured'] ? ' checked' : '' ?>> Feature on the home page</label>
        </div>
    </div>
    <p class="form-actions">
        <button class="btn btn-primary" type="submit">Save product</button>
        <a class="btn" href="<?= e(url('admin/products.php')) ?>">Cancel</a>
    </p>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
