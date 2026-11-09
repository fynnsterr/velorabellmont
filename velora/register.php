<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('account.php');
}

$next = safe_next(val('next'));
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim(val('name'));
    $email = strtolower(trim(val('email')));
    $phone = trim(val('phone'));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
        $errors['name'] = 'Enter your full name.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (!preg_match('/^\+?[0-9 ]{9,15}$/', $phone)) {
        $errors['phone'] = 'Enter a phone number, for example 024 123 4567.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Use at least 8 characters.';
    } elseif ($password !== $confirm) {
        $errors['confirm'] = 'The two passwords do not match.';
    }
    if (!$errors) {
        $st = db()->prepare('SELECT id FROM vb_users WHERE email = ?');
        $st->execute([$email]);
        if ($st->fetch()) {
            $errors['email'] = 'An account with this email already exists. Log in instead.';
        }
    }
    if (!$errors) {
        $ins = db()->prepare('INSERT INTO vb_users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?)');
        $ins->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), 'customer']);
        $row = ['id' => (int) db()->lastInsertId()];
        login_user($row);
        flash('success', 'Your account is ready, ' . $name . '.');
        redirect($next);
    }
}

$pageTitle = 'Create account';
require __DIR__ . '/includes/header.php';
?>
<section class="wrap narrow section">
    <h1>Create your account</h1>
    <?php if ($errors): ?><p class="flash flash-error" role="alert">Fix the highlighted fields and try again.</p><?php endif; ?>
    <form method="post" action="<?= e(url('register.php')) ?>" class="form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <?php
        $fields = [
            ['name', 'Full name', 'text', 'name'],
            ['email', 'Email', 'email', 'email'],
            ['phone', 'Phone number', 'tel', 'tel'],
            ['password', 'Password', 'password', 'new-password'],
            ['confirm', 'Confirm password', 'password', 'new-password'],
        ];
        foreach ($fields as [$key, $label, $type, $auto]):
            $isPass = $type === 'password';
        ?>
            <div class="field<?= isset($errors[$key]) ? ' has-error' : '' ?>">
                <label for="<?= e($key) ?>"><?= e($label) ?></label>
                <input id="<?= e($key) ?>" type="<?= e($type) ?>" name="<?= e($key) ?>" value="<?= $isPass ? '' : e(val($key)) ?>" autocomplete="<?= e($auto) ?>" required>
                <?php if (isset($errors[$key])): ?><p class="field-error"><?= e($errors[$key]) ?></p><?php endif; ?>
            </div>
        <?php endforeach; ?>
        <button class="btn btn-primary btn-block" type="submit">Create account</button>
    </form>
    <p class="muted">Already have an account? <a href="<?= e(url('login.php?next=' . urlencode($next))) ?>">Log in</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
