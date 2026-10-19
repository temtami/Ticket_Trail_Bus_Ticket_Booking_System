<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'add_type':
                $pdo->prepare('INSERT INTO bus_types (name, surcharge) VALUES (?, ?)')
                    ->execute([trim($_POST['name']), max(0, (int) $_POST['surcharge'])]);
                flash('success', 'Bus type added.');
                break;

            case 'add_bus':
                $seats = (int) $_POST['total_seats'];
                if ($seats < 8 || $seats > 60) throw new InvalidArgumentException('Seats must be between 8 and 60.');
                $pdo->prepare('INSERT INTO buses (reg_no, name, bus_type_id, total_seats) VALUES (?, ?, ?, ?)')
                    ->execute([strtoupper(trim($_POST['reg_no'])), trim($_POST['name']), (int) $_POST['bus_type_id'], $seats]);
                flash('success', 'Bus added.');
                break;

            case 'add_route':
                $src = trim($_POST['source']);
                $dst = trim($_POST['destination']);
                if ($src === '' || strcasecmp($src, $dst) === 0) throw new InvalidArgumentException('Source and destination must differ.');
                $pdo->prepare('INSERT INTO routes (source, destination, distance_km, base_fare) VALUES (?, ?, ?, ?)')
                    ->execute([$src, $dst, max(1, (int) $_POST['distance_km']), max(0, (int) $_POST['base_fare'])]);
                flash('success', 'Route added.');
                break;

            case 'add_schedule':
                $busId   = (int) $_POST['bus_id'];
                $routeId = (int) $_POST['route_id'];
                $dep = DateTime::createFromFormat('Y-m-d\TH:i', $_POST['departure'] ?? '');
                if (!$dep || $dep <= new DateTime()) throw new InvalidArgumentException('Departure must be a future date/time.');

                // Fare = route base fare + bus type surcharge (like the original t_price + r_price)
                $q = $pdo->prepare(
                    'SELECT r.base_fare + bt.surcharge FROM routes r, buses b JOIN bus_types bt ON bt.id = b.bus_type_id
                     WHERE r.id = ? AND b.id = ?'
                );
                $q->execute([$routeId, $busId]);
                $fare = $q->fetchColumn();
                if ($fare === false) throw new InvalidArgumentException('Invalid bus or route.');

                $pdo->prepare('INSERT INTO schedules (bus_id, route_id, departure, fare) VALUES (?, ?, ?, ?)')
                    ->execute([$busId, $routeId, $dep->format('Y-m-d H:i:s'), (int) $fare]);
                flash('success', 'Trip scheduled at ' . money((int) $fare) . ' per seat.');
                break;

            case 'delete_schedule':
                $pdo->prepare('DELETE FROM schedules WHERE id = ?')->execute([(int) $_POST['id']]);
                flash('success', 'Trip deleted.');
                break;
        }
    } catch (InvalidArgumentException $ex) {
        flash('error', $ex->getMessage());
    } catch (PDOException $ex) {
        if ($ex->getCode() !== '23000') throw $ex;
        flash('error', $action === 'delete_schedule'
            ? 'This trip has bookings and cannot be deleted.'
            : 'That entry already exists (duplicate name, registration number, route or departure).');
    }
    redirect('admin/index.php');
}

$stats = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM users WHERE role = 'user')                    AS users,
        (SELECT COUNT(*) FROM schedules WHERE departure > NOW())             AS upcoming,
        (SELECT COUNT(*) FROM bookings WHERE status = 'confirmed')           AS bookings,
        (SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE status = 'confirmed') AS revenue"
)->fetch();

$types  = $pdo->query('SELECT * FROM bus_types ORDER BY name')->fetchAll();
$buses  = $pdo->query('SELECT b.*, bt.name AS type_name FROM buses b JOIN bus_types bt ON bt.id = b.bus_type_id ORDER BY b.name')->fetchAll();
$routes = $pdo->query('SELECT * FROM routes ORDER BY source, destination')->fetchAll();
$trips  = $pdo->query(
    "SELECT s.*, b.name AS bus_name, b.total_seats, r.source, r.destination,
            (SELECT COUNT(*) FROM booking_seats bs WHERE bs.schedule_id = s.id) AS sold
     FROM schedules s JOIN buses b ON b.id = s.bus_id JOIN routes r ON r.id = s.route_id
     WHERE s.departure > NOW() ORDER BY s.departure"
)->fetchAll();
$recent = $pdo->query(
    "SELECT bk.*, u.name AS user_name, r.source, r.destination
     FROM bookings bk JOIN users u ON u.id = bk.user_id
     JOIN schedules s ON s.id = bk.schedule_id JOIN routes r ON r.id = s.route_id
     ORDER BY bk.created_at DESC LIMIT 10"
)->fetchAll();

$pageTitle = 'Admin';
require __DIR__ . '/../includes/header.php';
?>
<h1>Admin dashboard</h1>

<div class="stats">
    <div class="card stat"><span>Customers</span><strong><?= (int) $stats['users'] ?></strong></div>
    <div class="card stat"><span>Upcoming trips</span><strong><?= (int) $stats['upcoming'] ?></strong></div>
    <div class="card stat"><span>Active bookings</span><strong><?= (int) $stats['bookings'] ?></strong></div>
    <div class="card stat"><span>Revenue</span><strong><?= money((int) $stats['revenue']) ?></strong></div>
</div>

<div class="admin-forms">
    <form method="post" class="card">
        <h3>Schedule a trip</h3>
        <?= csrf_field() ?><input type="hidden" name="action" value="add_schedule">
        <label>Bus
            <select name="bus_id" required>
                <?php foreach ($buses as $b): ?>
                    <option value="<?= (int) $b['id'] ?>"><?= e($b['name'] . ' (' . $b['type_name'] . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Route
            <select name="route_id" required>
                <?php foreach ($routes as $r): ?>
                    <option value="<?= (int) $r['id'] ?>"><?= e($r['source'] . ' → ' . $r['destination']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Departure <input type="datetime-local" name="departure" required></label>
        <button class="btn btn-block">Add trip</button>
    </form>

    <form method="post" class="card">
        <h3>Add bus</h3>
        <?= csrf_field() ?><input type="hidden" name="action" value="add_bus">
        <label>Registration no. <input name="reg_no" required maxlength="20"></label>
        <label>Name <input name="name" required maxlength="60"></label>
        <label>Type
            <select name="bus_type_id" required>
                <?php foreach ($types as $t): ?>
                    <option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Total seats <input type="number" name="total_seats" min="8" max="60" step="4" value="40" required></label>
        <button class="btn btn-block">Add bus</button>
    </form>

    <form method="post" class="card">
        <h3>Add route</h3>
        <?= csrf_field() ?><input type="hidden" name="action" value="add_route">
        <label>From <input name="source" required maxlength="50"></label>
        <label>To <input name="destination" required maxlength="50"></label>
        <label>Distance (km) <input type="number" name="distance_km" min="1" required></label>
        <label>Base fare (₹) <input type="number" name="base_fare" min="0" required></label>
        <button class="btn btn-block">Add route</button>
    </form>

    <form method="post" class="card">
        <h3>Add bus type</h3>
        <?= csrf_field() ?><input type="hidden" name="action" value="add_type">
        <label>Name <input name="name" required maxlength="40"></label>
        <label>Surcharge (₹) <input type="number" name="surcharge" min="0" value="0" required></label>
        <button class="btn btn-block">Add type</button>
    </form>
</div>

<h2>Upcoming trips</h2>
<div class="table-wrap">
<table>
    <thead><tr><th>Route</th><th>Bus</th><th>Departure</th><th>Fare</th><th>Sold</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($trips as $t): ?>
        <tr>
            <td><?= e($t['source']) ?> → <?= e($t['destination']) ?></td>
            <td><?= e($t['bus_name']) ?></td>
            <td><?= e(fmt_datetime($t['departure'])) ?></td>
            <td><?= money((int) $t['fare']) ?></td>
            <td><?= (int) $t['sold'] ?>/<?= (int) $t['total_seats'] ?></td>
            <td>
                <form method="post" onsubmit="return confirm('Delete this trip?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_schedule">
                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                    <button class="btn btn-small btn-danger">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<h2>Recent bookings</h2>
<div class="table-wrap">
<table>
    <thead><tr><th>Customer</th><th>Trip</th><th>Seats</th><th>Amount</th><th>Ref</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $bk): ?>
        <tr>
            <td><?= e($bk['user_name']) ?></td>
            <td><?= e($bk['source']) ?> → <?= e($bk['destination']) ?></td>
            <td><?= (int) $bk['seat_count'] ?></td>
            <td><?= money((int) $bk['total_amount']) ?></td>
            <td><code><?= e($bk['payment_ref']) ?></code></td>
            <td><span class="badge <?= $bk['status'] === 'confirmed' ? 'badge-green' : 'badge-red' ?>"><?= e(ucfirst($bk['status'])) ?></span></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>