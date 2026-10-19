<?php $user = current_user(); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($pageTitle ?? 'Home') . ' · TicketTrail') ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<header class="topbar">
    <div class="container nav">
        <a class="brand" href="<?= e(url('index.php')) ?>">🚌 TicketTrail</a>
        <nav>
            <a href="<?= e(url('index.php')) ?>">Search</a>
            <?php if ($user): ?>
                <a href="<?= e(url('my_bookings.php')) ?>">My bookings</a>
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="<?= e(url('admin/index.php')) ?>">Admin</a>
                <?php endif; ?>
                <a href="<?= e(url('logout.php')) ?>">Logout (<?= e($user['name']) ?>)</a>
            <?php else: ?>
                <a href="<?= e(url('login.php')) ?>">Login</a>
                <a class="btn btn-small" href="<?= e(url('register.php')) ?>">Sign up</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container">
<?php foreach (take_flashes() as [$type, $msg]): ?>
    <div class="alert alert-<?= e($type) ?>"><?= e($msg) ?></div>
<?php endforeach; ?>