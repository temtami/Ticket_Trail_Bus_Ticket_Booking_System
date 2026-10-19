<?php
require_once __DIR__ . '/includes/functions.php';

$sources      = $pdo->query('SELECT DISTINCT source FROM routes ORDER BY source')->fetchAll(PDO::FETCH_COLUMN);
$destinations = $pdo->query('SELECT DISTINCT destination FROM routes ORDER BY destination')->fetchAll(PDO::FETCH_COLUMN);

$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to'] ?? '');
$date = trim($_GET['date'] ?? '');

$sql = "SELECT s.id, s.departure, s.fare, b.name AS bus_name, b.total_seats, bt.name AS bus_type,
               r.source, r.destination, r.distance_km,
               b.total_seats - (SELECT COUNT(*) FROM booking_seats bs WHERE bs.schedule_id = s.id) AS seats_left
        FROM schedules s
        JOIN buses b      ON b.id = s.bus_id
        JOIN bus_types bt ON bt.id = b.bus_type_id
        JOIN routes r     ON r.id = s.route_id
        WHERE s.departure > NOW()";
$params = [];

if ($from !== '') { $sql .= ' AND r.source = ?';      $params[] = $from; }
if ($to !== '')   { $sql .= ' AND r.destination = ?'; $params[] = $to; }
if ($date !== '' && DateTime::createFromFormat('Y-m-d', $date)) {
    $sql .= ' AND DATE(s.departure) = ?';
    $params[] = $date;
}
$sql .= ' ORDER BY s.departure LIMIT 50';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$trips = $stmt->fetchAll();

$pageTitle = 'Search buses';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <h1>Where are you headed?</h1>
    <form class="search" method="get">
        <select name="from">
            <option value="">From (any)</option>
            <?php foreach ($sources as $s): ?>
                <option <?= $s === $from ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="to">
            <option value="">To (any)</option>
            <?php foreach ($destinations as $d): ?>
                <option <?= $d === $to ? 'selected' : '' ?>><?= e($d) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date" value="<?= e($date) ?>" min="<?= date('Y-m-d') ?>">
        <button class="btn" type="submit">Search</button>
    </form>
</section>

<h2><?= ($from || $to || $date) ? 'Matching trips' : 'Upcoming trips' ?></h2>

<?php if (!$trips): ?>
    <p class="muted">No trips found. Try a different date or route.</p>
<?php endif; ?>

<div class="cards">
<?php foreach ($trips as $t): ?>
    <article class="card trip">
        <div>
            <h3><?= e($t['source']) ?> → <?= e($t['destination']) ?></h3>
            <p class="muted"><?= e($t['bus_name']) ?> · <?= e($t['bus_type']) ?> · <?= (int) $t['distance_km'] ?> km</p>
            <p><?= e(fmt_datetime($t['departure'])) ?></p>
        </div>
        <div class="trip-side">
            <strong class="price"><?= money((int) $t['fare']) ?></strong>
            <span class="muted"><?= (int) $t['seats_left'] ?> of <?= (int) $t['total_seats'] ?> seats left</span>
            <?php if ($t['seats_left'] > 0): ?>
                <a class="btn" href="<?= e(url('book.php?schedule=' . $t['id'])) ?>">Select seats</a>
            <?php else: ?>
                <span class="badge badge-red">Sold out</span>
            <?php endif; ?>
        </div>
    </article>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>