<?php
require_once __DIR__ . '/includes/functions.php';

$hasAdmin = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() > 0;
$errors = [];

if (!$hasAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name  = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    if ($name === '')                                   $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))     $errors[] = 'Valid email is required.';
    if (strlen($pass) < 8)                              $errors[] = 'Password must be at least 8 characters.';

    if (!$errors) {
        $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, '0000000', ?, 'admin')")
            ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
        flash('success', 'Admin created. Log in, then delete setup.php.');
        redirect('login.php');
    }
}

$pageTitle = 'Setup';
require __DIR__ . '/includes/header.php';
?>
<div class="auth card">
    <h1>First-time setup</h1>
    <?php if ($hasAdmin): ?>
        <p>An admin already exists. You can safely delete <code>setup.php</code>.</p>
    <?php else: ?>
        <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post">
            <?= csrf_field() ?>
            <label>Admin name <input name="name" required></label>
            <label>Admin email <input type="email" name="email" required></label>
            <label>Password <input type="password" name="password" minlength="8" required></label>
            <button class="btn btn-block">Create admin</button>
        </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>