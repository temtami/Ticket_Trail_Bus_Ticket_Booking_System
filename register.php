<?php
require_once __DIR__ . '/includes/functions.php';
if (current_user()) redirect('index.php');

$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old['name']  = trim($_POST['name'] ?? '');
    $old['email'] = strtolower(trim($_POST['email'] ?? ''));
    $old['phone'] = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if ($old['name'] === '' || mb_strlen($old['name']) > 60) $errors[] = 'Enter your name (max 60 characters).';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))    $errors[] = 'Enter a valid email.';
    if (!preg_match('/^\+?[0-9]{7,15}$/', $old['phone']))      $errors[] = 'Enter a valid phone number (digits only).';
    if (strlen($password) < 8)                                 $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)                                $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $exists = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
        $exists->execute([$old['email']]);
        if ($exists->fetch()) {
            $errors[] = 'An account with that email already exists.';
        } else {
            $pdo->prepare('INSERT INTO users (name, email, phone, password_hash) VALUES (?, ?, ?, ?)')
                ->execute([$old['name'], $old['email'], $old['phone'], password_hash($password, PASSWORD_DEFAULT)]);
            flash('success', 'Account created. You can log in now.');
            redirect('login.php');
        }
    }
}

$pageTitle = 'Sign up';
require __DIR__ . '/includes/header.php';
?>
<div class="auth card">
    <h1>Create an account</h1>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label>Name <input name="name" value="<?= e($old['name']) ?>" required></label>
        <label>Email <input type="email" name="email" value="<?= e($old['email']) ?>" required></label>
        <label>Phone <input name="phone" value="<?= e($old['phone']) ?>" required></label>
        <label>Password <input type="password" name="password" minlength="8" required></label>
        <label>Confirm password <input type="password" name="confirm" minlength="8" required></label>
        <button class="btn btn-block">Sign up</button>
    </form>
    <p class="muted">Already registered? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>