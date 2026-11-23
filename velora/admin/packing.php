<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$ids = array_filter(array_map('intval', explode(',', val('id'))));
$orders = packing_orders($ids, val('status'));
$label = val('mode') === 'label';
log_activity('print', $label ? 'labels' : 'packing slips', count($orders) . ' order(s)');
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $label ? 'Shipping labels' : 'Packing slips' ?> | <?= e(cfg('site.name')) ?></title>
<style>
body{font-family:system-ui,sans-serif;color:#111;margin:0;padding:1rem;line-height:1.4}
.bar{margin-bottom:1rem}
.bar button{font:inherit;padding:.5rem 1rem;cursor:pointer}
.slip{border:1px solid #999;padding:1.2rem;margin-bottom:1rem;page-break-after:always;break-inside:avoid}
.slip h1{font-size:1.2rem;margin:0 0 .2rem}
.slip table{width:100%;border-collapse:collapse;margin:.8rem 0}
.slip th,.slip td{text-align:left;padding:.3rem .4rem;border-bottom:1px solid #ccc;font-size:.95rem}
.slip .q{width:3rem;text-align:center}
.cod{border:2px solid #111;padding:.4rem .6rem;font-weight:700;display:inline-block;margin:.4rem 0}
.labels{display:grid;grid-template-columns:1fr 1fr;gap:.6rem}
.lab{border:1px dashed #666;padding:.9rem;break-inside:avoid;min-height:9rem}
.lab .to{font-size:1.15rem;font-weight:700}
.lab .from{font-size:.8rem;color:#444;margin-top:.6rem}
@media print{.bar{display:none}body{padding:0}.slip{border:0}}
</style>
</head>
<body>
<div class="bar"><button type="button" onclick="window.print()">Print</button> <?= count($orders) ?> order(s). <?= $label ? 'Shipping labels' : 'Packing slips' ?>.</div>
<?php if (!$orders): ?><p>No orders to print.</p><?php endif; ?>
<?php if ($label): ?>
<div class="labels">
<?php foreach ($orders as $o): $cod = $o['payment_method'] === 'cod' && $o['payment_status'] !== 'paid'; ?>
    <div class="lab">
        <div class="to"><?= e($o['full_name']) ?></div>
        <div><?= e($o['address']) ?><br><?= e($o['city']) ?>, <?= e($o['region']) ?></div>
        <div><strong><?= e($o['phone']) ?></strong></div>
        <?php if ($cod): ?><div class="cod">COLLECT <?= e(money($o['total'])) ?></div><?php endif; ?>
        <div class="from">Order <?= e($o['order_ref']) ?> · From <?= e(cfg('site.name')) ?>, <?= e(cfg('site.support_phone')) ?></div>
    </div>
<?php endforeach; ?>
</div>
<?php else: ?>
<?php foreach ($orders as $o): $cod = $o['payment_method'] === 'cod' && $o['payment_status'] !== 'paid'; ?>
    <section class="slip">
        <h1><?= e(cfg('site.name')) ?> · Packing slip</h1>
        <div>Order <strong><?= e($o['order_ref']) ?></strong> · <?= e(date('j M Y', strtotime((string) $o['created_at']))) ?></div>
        <p><strong><?= e($o['full_name']) ?></strong><br><?= e($o['address']) ?><br><?= e($o['city']) ?>, <?= e($o['region']) ?><br><?= e($o['phone']) ?></p>
        <?php if ($cod): ?><div class="cod">PAY ON DELIVERY: collect <?= e(money($o['total'])) ?></div><?php endif; ?>
        <table>
            <thead><tr><th class="q">Qty</th><th>Item</th><th>Size</th><th class="q">Packed</th></tr></thead>
            <tbody>
            <?php foreach ($o['items'] as $it): ?>
                <tr><td class="q"><?= (int) $it['quantity'] ?></td><td><?= e($it['product_name']) ?></td><td><?= e((string) ($it['size'] ?? '')) ?></td><td class="q">[ ]</td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!empty($o['notes'])): ?><p><strong>Customer note:</strong> <?= e($o['notes']) ?></p><?php endif; ?>
        <p>Thank you for shopping with us. Questions: <?= e(cfg('site.support_email')) ?> · <?= e(cfg('site.support_phone')) ?></p>
    </section>
<?php endforeach; ?>
<?php endif; ?>
</body>
</html>
