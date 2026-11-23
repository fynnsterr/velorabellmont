<?php
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$topics = contact_topics();
$d = [
    'name' => $user['name'] ?? '',
    'email' => $user['email'] ?? '',
    'phone' => $user['phone'] ?? '',
    'topic' => isset($topics[val('topic')]) ? val('topic') : 'general',
    'order_ref' => strtoupper(val('order')),
    'message' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    [$d, $errors] = contact_validate($_POST);
    if (trim(str_in($_POST, 'website')) !== '') {
        flash('success', 'Thanks, your message has been sent.');
        redirect('contact.php');
    }
    if (!$errors && (!contact_rate_ok($d['email']) || !throttle_hit('contact:' . client_ip(), 6, 3600))) {
        $errors['message'] = 'You have sent a few messages already. Please wait a little before sending another, or message us on WhatsApp.';
    }
    if (!$errors) {
        $id = contact_save($d, $user ? (int) $user['id'] : null);
        contact_note_sent();
        contact_notify($id);
        flash('success', 'Thanks, your message has been sent. We will reply to ' . $d['email'] . '.');
        redirect('contact.php');
    }
}

$hours = trim((string) cfg('site.hours', ''));
$addr = trim((string) cfg('site.address', ''));
$socials = social_links();
$pageTitle = 'Contact us';
$metaDesc = 'Contact Velora Bellmont by message form, email, phone or WhatsApp. Questions about a piece, an order, a return or a gift.';
$jsonld = [organization_jsonld(), breadcrumb_jsonld([['Home', abs_url('')], ['Contact', abs_url('contact.php')]])];
require __DIR__ . '/includes/header.php';
?>
<section class="wrap section">
    <div class="page-head">
        <p class="eyebrow eyebrow-dark">Contact</p>
        <h1>Contact us</h1>
        <p class="lede">Questions about a piece, an order, a return or a gift? Send a message and we will reply by email, or reach us on WhatsApp.</p>
    </div>
    <div class="contact-layout">
        <form method="post" action="<?= e(url('contact.php')) ?>" class="form" novalidate>
            <?= csrf_field() ?>
            <div class="field hp" aria-hidden="true">
                <label for="website">Leave this empty</label>
                <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
            </div>
            <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
                <label for="name">Your name</label>
                <input id="name" type="text" name="name" value="<?= e($d['name']) ?>" autocomplete="name" required<?= isset($errors['name']) ? ' aria-describedby="name-err"' : '' ?>>
                <?php if (isset($errors['name'])): ?><p class="field-error" id="name-err"><?= e($errors['name']) ?></p><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="<?= e($d['email']) ?>" autocomplete="email" required<?= isset($errors['email']) ? ' aria-describedby="email-err"' : '' ?>>
                <?php if (isset($errors['email'])): ?><p class="field-error" id="email-err"><?= e($errors['email']) ?></p><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['phone']) ? ' has-error' : '' ?>">
                <label for="phone">Phone (optional)</label>
                <input id="phone" type="tel" name="phone" value="<?= e($d['phone']) ?>" autocomplete="tel"<?= isset($errors['phone']) ? ' aria-describedby="phone-err"' : '' ?>>
                <?php if (isset($errors['phone'])): ?><p class="field-error" id="phone-err"><?= e($errors['phone']) ?></p><?php endif; ?>
            </div>
            <div class="field">
                <label for="topic">What is it about?</label>
                <select id="topic" name="topic">
                    <?php foreach ($topics as $k => $label): ?><option value="<?= e($k) ?>"<?= $d['topic'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field<?= isset($errors['order_ref']) ? ' has-error' : '' ?>">
                <label for="order_ref">Order number (optional)</label>
                <input id="order_ref" type="text" name="order_ref" value="<?= e($d['order_ref']) ?>" placeholder="VB-260101-ABC123" maxlength="20"<?= isset($errors['order_ref']) ? ' aria-describedby="order-err"' : '' ?>>
                <?php if (isset($errors['order_ref'])): ?><p class="field-error" id="order-err"><?= e($errors['order_ref']) ?></p><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['message']) ? ' has-error' : '' ?>">
                <label for="message">Your message</label>
                <textarea id="message" name="message" rows="6" maxlength="2000" required<?= isset($errors['message']) ? ' aria-describedby="message-err"' : '' ?>><?= e($d['message']) ?></textarea>
                <?php if (isset($errors['message'])): ?><p class="field-error" id="message-err"><?= e($errors['message']) ?></p><?php endif; ?>
            </div>
            <button class="btn btn-primary btn-lg" type="submit">Send message</button>
        </form>

        <aside class="contact-side" aria-label="Other ways to reach us">
            <h2>Other ways to reach us</h2>
            <ul class="contact-list">
                <li><span class="k">Email</span><a href="mailto:<?= e(cfg('site.support_email')) ?>"><?= e(cfg('site.support_email')) ?></a></li>
                <li><span class="k">Phone</span><a href="tel:<?= e(preg_replace('/[^+0-9]/', '', (string) cfg('site.support_phone'))) ?>"><?= e(cfg('site.support_phone')) ?></a></li>
                <?php if (wa_number() !== ''): ?><li><span class="k">WhatsApp</span><a href="<?= e(wa_url('Hello ' . cfg('site.name') . ', I have a question.')) ?>" target="_blank" rel="noopener">Chat with us<span class="sr-only"> (opens in a new tab)</span></a></li><?php endif; ?>
                <?php if ($hours !== ''): ?><li><span class="k">Hours</span><?= e($hours) ?></li><?php endif; ?>
                <?php if ($addr !== ''): ?><li><span class="k">Address</span><?= e($addr) ?></li><?php endif; ?>
                <?php foreach ($socials as $label => $href): ?><li><span class="k"><?= e($label) ?></span><a href="<?= e($href) ?>" target="_blank" rel="noopener me"><?= e(preg_replace('#^https?://(www\.)?#', '', $href)) ?><span class="sr-only"> (opens in a new tab)</span></a></li><?php endforeach; ?>
            </ul>
            <p class="muted">Looking for a quick answer? Read the <a href="<?= e(url('faq.php')) ?>">FAQ</a>, <a href="<?= e(url('delivery.php')) ?>">delivery</a> and <a href="<?= e(url('returns.php')) ?>">returns</a> pages.</p>
        </aside>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
