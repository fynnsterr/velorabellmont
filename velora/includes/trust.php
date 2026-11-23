<?php

function return_days(): int
{
    return max(1, (int) cfg('site.return_days', 7));
}

function refund_days(): int
{
    return max(1, (int) cfg('site.refund_days', 5));
}

function social_links(): array
{
    $labels = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'x' => 'X', 'youtube' => 'YouTube', 'whatsapp_channel' => 'WhatsApp channel'];
    $out = [];
    foreach ($labels as $key => $label) {
        $u = trim((string) cfg('social.' . $key, ''));
        if ($u !== '' && preg_match('#^https://#i', $u) && filter_var($u, FILTER_VALIDATE_URL) && ($key !== 'whatsapp_channel' || preg_match('#^https://(www\.)?(whatsapp\.com|wa\.me)/#i', $u))) {
            $out[$label] = $u;
        }
    }
    return $out;
}

function trust_points(): array
{
    return [
        ['title' => 'Delivery across Ghana', 'text' => 'Every region, with tracking once it ships.', 'href' => 'delivery.php'],
        ['title' => return_days() . ' day returns', 'text' => 'Unworn pieces, refund or exchange.', 'href' => 'returns.php'],
        ['title' => 'Secure payment', 'text' => 'Card and mobile money through Paystack.', 'href' => 'policies.php#payments'],
        ['title' => 'Real people on WhatsApp', 'text' => cfg('site.hours', 'Ask us anything.'), 'href' => 'contact.php'],
    ];
}

function payment_badges(): array
{
    $b = ['Visa', 'Mastercard', 'MTN MoMo', 'Telecel Cash', 'AT Money', 'Pay on delivery'];
    return $b;
}

function secure_connection_note(): string
{
    return is_https() ? 'This page is sent over an encrypted HTTPS connection.' : '';
}

function materials_verified_list(): array
{
    $st = db()->query("SELECT DISTINCT materials FROM vb_products WHERE active = 1 AND materials_verified = 1 AND materials IS NOT NULL AND materials <> '' ORDER BY materials");
    return array_map(fn ($r) => (string) $r['materials'], $st->fetchAll());
}

function product_material(array $p): ?string
{
    if ((int) ($p['materials_verified'] ?? 0) === 1 && trim((string) ($p['materials'] ?? '')) !== '') {
        return trim((string) $p['materials']);
    }
    return null;
}

function packaging_photos(): array
{
    $dir = VB_ROOT . '/assets/img/packaging';
    $out = [];
    foreach (glob($dir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [] as $f) {
        $name = pathinfo($f, PATHINFO_FILENAME);
        $out[] = ['image' => 'img/packaging/' . basename($f), 'alt' => ucfirst(trim(preg_replace('/[-_]+/', ' ', $name)))];
    }
    sort($out);
    return $out;
}

function care_sections(): array
{
    return [
        ['id' => 'daily', 'title' => 'Everyday care', 'items' => [
            'Put your jewelry on last, after perfume, lotion, hairspray and make-up, and take it off first.',
            'Remove it before swimming, showering, exercising and sleeping. Water, sweat and chlorine wear plated finishes down faster.',
            'Wipe each piece with a soft, dry cloth after wearing to remove oils and sweat.',
        ]],
        ['id' => 'storage', 'title' => 'Storing your pieces', 'items' => [
            'Keep each piece separate, in the pouch or box it came in, so chains do not tangle and stones do not scratch other pieces.',
            'Store in a cool, dry place away from direct sunlight and the bathroom.',
            'Fasten necklaces and bracelets before storing to stop them knotting.',
        ]],
        ['id' => 'cleaning', 'title' => 'Cleaning', 'items' => [
            'Use a soft, dry microfibre cloth. For light marks, dampen the cloth very slightly with water, then dry the piece straight away.',
            'Do not use jewelry dips, toothpaste, baking soda, alcohol, bleach or ultrasonic cleaners. They can strip plating and loosen stones.',
            'Stones set in glue should never be soaked.',
        ]],
        ['id' => 'wear', 'title' => 'What to expect over time', 'items' => [
            'Fashion jewelry finishes wear gradually with contact, moisture and skin chemistry. How long a finish lasts depends on how often it is worn and how it is looked after.',
            'A slight change in colour at the points that touch your skin most is normal for plated and coated finishes.',
            'If a piece arrives faulty or damaged, tell us within 48 hours. See Returns and exchanges.',
        ]],
    ];
}

function finish_terms(): array
{
    return [
        ['term' => 'Gold tone', 'meaning' => 'The finish is gold in colour. It does not mean the piece contains gold.'],
        ['term' => 'Rose gold tone', 'meaning' => 'The finish is a pink gold colour. It does not mean the piece contains gold.'],
        ['term' => 'Silver tone', 'meaning' => 'The finish is silver in colour. It does not mean the piece is sterling silver.'],
    ];
}

function faq_items(): array
{
    $free = money(cfg('shipping.free_over'));
    $hours = (string) cfg('site.hours', '');
    return [
        'Ordering and payment' => [
            ['q' => 'How do I pay?', 'a' => 'You can pay by card or mobile money (MTN MoMo, Telecel Cash, AT Money) through Paystack, or pay on delivery in ' . cod_regions_text() . '. Card and mobile money details are entered on Paystack. They never reach our website and we do not store them.'],
            ['q' => 'Is it safe to pay on your website?', 'a' => 'Payments are handled by Paystack, a payment provider used by many online shops in Ghana. Our website never sees your card number or mobile money PIN. Your account password is stored as a one-way hash that nobody here can read.'],
            ['q' => 'Do I need an account to order?', 'a' => 'No. You can check out as a guest and track the order with your order number and email. An account lets you save addresses, wishlist pieces and earn Velora Rewards points.'],
            ['q' => 'Can I change or cancel my order?', 'a' => 'Contact us as soon as you can. If the order has not been dispatched we can change or cancel it. Once it is dispatched, you can return it when it arrives (see Returns).'],
        ],
        'Delivery' => [
            ['q' => 'How long does delivery take?', 'a' => 'It depends on your region. Greater Accra is quickest. The delivery page lists the fee and the working days for every region, and checkout shows your estimate before you pay.'],
            ['q' => 'How much is delivery?', 'a' => 'Delivery is charged by region and is free on orders over ' . $free . '. You see the exact fee at checkout before you pay.'],
            ['q' => 'How do I track my order?', 'a' => 'Use Track an order with your order number and email, or log in to your account. You will see each step: order received, payment confirmed, processing, dispatched, out for delivery and delivered, with the courier and tracking number once it ships. We also email you at each stage.'],
        ],
        'Returns and exchanges' => [
            ['q' => 'Can I return a piece?', 'a' => 'Yes. Return unworn pieces in their original packaging within ' . return_days() . ' days of delivery for a refund or an exchange. Earrings can only be returned if they are unworn and sealed, for hygiene reasons.'],
            ['q' => 'What if my piece arrives damaged or wrong?', 'a' => 'Tell us within 48 hours with a photo and we will replace it at no cost.'],
            ['q' => 'How long do refunds take?', 'a' => 'Once we have received and checked the return, we aim to send your refund within ' . refund_days() . ' working days, to the same method you paid with where we can.'],
        ],
        'Our jewelry' => [
            ['q' => 'What are your pieces made of?', 'a' => 'Most of our pieces have a gold tone, rose gold tone or silver tone finish. These words describe the colour of the finish, not the metal underneath. We only name a specific material on a product page once we have confirmed it with our supplier. See Materials and care for how to look after your jewelry.'],
            ['q' => 'How do I find my ring size?', 'a' => 'Choose your size on the product page. Wrap a thin strip of paper around the base of your finger, mark where it overlaps and measure the length, or measure the inside diameter of a ring that fits you. If you are between sizes, choose the larger one. Rings can be exchanged for another size.'],
            ['q' => 'How should I look after my jewelry?', 'a' => 'Keep it dry, put it on last and take it off first, and store each piece separately. The care guide has the full advice.'],
        ],
        'Rewards, gift cards and contact' => [
            ['q' => 'How do Velora Rewards work?', 'a' => 'You earn points on paid orders, for referring friends and for reviewing pieces you bought. Points turn into money off your next order. Details are on the Rewards page.'],
            ['q' => 'How can I contact you?', 'a' => 'Use the Contact page, email ' . cfg('site.support_email') . ' or message us on WhatsApp.' . ($hours !== '' ? ' We reply ' . $hours . '.' : '')],
        ],
    ];
}

function contact_topics(): array
{
    return [
        'general' => 'General question',
        'order' => 'My order',
        'product' => 'About a piece',
        'return' => 'Return or exchange',
        'wholesale' => 'Gifting or bulk orders',
        'other' => 'Something else',
    ];
}

function contact_validate(array $in): array
{
    $d = [
        'name' => trim((string) ($in['name'] ?? '')),
        'email' => strtolower(trim((string) ($in['email'] ?? ''))),
        'phone' => trim((string) ($in['phone'] ?? '')),
        'topic' => (string) ($in['topic'] ?? 'general'),
        'order_ref' => strtoupper(trim((string) ($in['order_ref'] ?? ''))),
        'message' => trim((string) ($in['message'] ?? '')),
    ];
    $e = [];
    if (mb_strlen($d['name']) < 2 || mb_strlen($d['name']) > 120) {
        $e['name'] = 'Enter your name.';
    }
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || strlen($d['email']) > 190) {
        $e['email'] = 'Enter a valid email address so we can reply.';
    }
    if ($d['phone'] !== '' && !preg_match('/^[0-9+() \-]{7,20}$/', $d['phone'])) {
        $e['phone'] = 'Enter a phone number with digits only, or leave it empty.';
    }
    if (!isset(contact_topics()[$d['topic']])) {
        $d['topic'] = 'general';
    }
    if ($d['order_ref'] !== '' && !preg_match('/^VB-\d{6}-[0-9A-F]{6}$/', $d['order_ref'])) {
        $e['order_ref'] = 'Order numbers look like VB-260101-ABC123.';
    }
    $len = mb_strlen($d['message']);
    if ($len < 10) {
        $e['message'] = 'Tell us a little more (at least 10 characters).';
    } elseif ($len > 2000) {
        $e['message'] = 'Please keep it under 2000 characters.';
    }
    return [$d, $e];
}

function contact_rate_ok(string $email): bool
{
    $now = time();
    $recent = array_values(array_filter((array) ($_SESSION['contact_times'] ?? []), fn ($t) => $t > $now - 600));
    if (count($recent) >= 3) {
        $_SESSION['contact_times'] = $recent;
        return false;
    }
    $st = db()->prepare('SELECT COUNT(*) FROM vb_messages WHERE email = ? AND created_at >= ?');
    $st->execute([$email, date('Y-m-d H:i:s', $now - 3600)]);
    return (int) $st->fetchColumn() < 5;
}

function contact_note_sent(): void
{
    $_SESSION['contact_times'][] = time();
}

function contact_save(array $d, ?int $userId = null): int
{
    db()->prepare('INSERT INTO vb_messages (name, email, phone, topic, order_ref, message, status, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$d['name'], $d['email'], $d['phone'] !== '' ? $d['phone'] : null, $d['topic'], $d['order_ref'] !== '' ? $d['order_ref'] : null, $d['message'], 'new', $userId]);
    return (int) db()->lastInsertId();
}

function contact_notify(int $id): void
{
    $m = enquiry_find($id);
    if (!$m) {
        return;
    }
    $to = (string) cfg('site.support_email', '');
    $topics = contact_topics();
    if ($to !== '') {
        $body = "New message from the website.\n\nName: " . $m['name'] . "\nEmail: " . $m['email'] . "\nPhone: " . ($m['phone'] ?: 'not given') . "\nTopic: " . ($topics[$m['topic']] ?? $m['topic']) . "\nOrder: " . ($m['order_ref'] ?: 'not given') . "\n\n" . $m['message'] . "\n\nReply to this email to answer them, or open it in Admin, Enquiries: " . abs_url('admin/enquiries.php');
        send_mail($to, 'Website message: ' . ($topics[$m['topic']] ?? 'General question') . ' from ' . $m['name'], $body, ['type' => 'contact', 'reply_to' => $m['email']]);
    }
    send_mail($m['email'], 'We got your message', 'Hi ' . $m['name'] . ",\n\nThanks for getting in touch. We have your message and will reply" . (trim((string) cfg('site.hours', '')) !== '' ? ' (we are available ' . cfg('site.hours') . ')' : ' soon') . ".\n\nYour message:\n" . $m['message'] . "\n\nVelora Bellmont", ['type' => 'contact_ack']);
}

function enquiry_find(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM vb_messages WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function enquiries_list(string $status = 'all', string $search = ''): array
{
    $sql = 'SELECT * FROM vb_messages WHERE 1 = 1';
    $args = [];
    if (in_array($status, ['new', 'replied', 'closed'], true)) {
        $sql .= ' AND status = ?';
        $args[] = $status;
    }
    if ($search !== '') {
        $sql .= ' AND (name LIKE ? OR email LIKE ? OR message LIKE ? OR order_ref LIKE ?)';
        $like = '%' . $search . '%';
        array_push($args, $like, $like, $like, $like);
    }
    $st = db()->prepare($sql . ' ORDER BY id DESC LIMIT 200');
    $st->execute($args);
    return $st->fetchAll();
}

function enquiry_set_status(int $id, string $status): bool
{
    if (!in_array($status, ['new', 'replied', 'closed'], true) || !enquiry_find($id)) {
        return false;
    }
    db()->prepare('UPDATE vb_messages SET status = ?, handled_at = ' . ($status === 'new' ? 'NULL' : 'CURRENT_TIMESTAMP') . ' WHERE id = ?')->execute([$status, $id]);
    return true;
}

function enquiry_new_count(): int
{
    return (int) db()->query("SELECT COUNT(*) FROM vb_messages WHERE status = 'new'")->fetchColumn();
}
