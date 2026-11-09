<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('account.php');
}

$next = safe_next(val('next'));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = strtolower(trim(val('email')));
    $password = (string) ($_POST['password'] ?? '');

    if (($_SESSION['login_lock'] ?? 0) > time()) {
        $error = 'Too many attempts. Wait a minute, then try again.';
    } else {
        $st = db()->prepare('SELECT * FROM vb_users WHERE email = ?');
        $st->execute([$email]);
        $row = $st->fetch();
        if ($row && password_verify($password, $row['password_hash'])) {
            unset($_SESSION['login_fails'], $_SESSION['login_lock']);
            login_user($row);
            flash('success', 'Welcome back, ' . $row['name'] . '.');
            redirect($row['role'] === 'admin' ? 'admin/index.php' : $next);
        }
        $_SESSION['login_fails'] = (int) ($_SESSION['login_fails'] ?? 0) + 1;
        if ($_SESSION['login_fails'] >= 5) {
            $_SESSION['login_lock'] = time() + 60;
            $_SESSION['login_fails'] = 0;
        }
        $error = 'That email and password do not match. Check them and try again.';
    }
}

$pageTitle = 'Log in';
require __DIR__ . '/includes/header.php';
?>
<section class="wrap narrow section">
    <h1>Log in</h1>
    <?php if ($error): ?><p class="flash flash-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post" action="<?= e(url('login.php')) ?>" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="<?= e(val('email')) ?>" autocomplete="email" required>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" autocomplete="current-password" required>
        </div>
        <button class="btn btn-primary btn-block" type="submit">Log in</button>
    </form>
    <p class="muted">New here? <a href="<?= e(url('register.php?next=' . urlencode($next))) ?>">Create an account</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
