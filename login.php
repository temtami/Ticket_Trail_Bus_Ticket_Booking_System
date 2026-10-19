<?php
require_once __DIR__ . '/includes/functions.php';
if (current_user()) redirect('index.php');

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT id, name, email, role, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        unset($user['password_hash']);
        $_SESSION['user'] = $user;

        $next = $_SESSION['after_login'] ?? null;
        unset($_SESSION['after_login']);
        if ($next && str_starts_with($next, '/') && !str_starts_with($next, '//')) {
            header('Location: ' . $next);
            exit;
        }
        redirect($user['role'] === 'admin' ? 'admin/index.php' : 'index.php');
    }
    $error = 'Incorrect email or password.';
}

$pageTitle = 'Log in';
require __DIR__ . '/includes/header.php';
?>
<div class="auth card">
    <h1>Welcome back</h1>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label>Email <input type="email" name="email" value="<?= e($email) ?>" required autofocus></label>
        <label>Password <input type="password" name="password" required></label>
        <button class="btn btn-block">Log in</button>
    </form>
    <p class="muted">New here? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>