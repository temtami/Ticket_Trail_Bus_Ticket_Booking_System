<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$userId = current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) {
    verify_csrf();
    $bookingId = (int) $_POST['booking_id'];

    $check = $pdo->prepare(
        "SELECT bk.id FROM bookings bk
         JOIN schedules s ON s.id = bk.schedule_id
         WHERE bk.id = ? AND bk.user_id = ? AND bk.status = 'confirmed' AND s.departure > NOW()"
    );
    $check->execute([$bookingId, $userId]);

    if ($check->fetch()) {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM booking_seats WHERE booking_id = ?')->execute([$bookingId]);
        $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")->execute([$bookingId]);
        $pdo->commit();
        flash('success', 'Booking cancelled and seats released.');
    } else {
        flash('error', 'That booking cannot be cancelled.');
    }
    redirect('my_bookings.php');
}

$stmt = $pdo->prepare(
    "SELECT bk.*, s.departure, r.source, r.destination, b.name AS bus_name,
            (s.departure > NOW()) AS is_upcoming,
            (SELECT GROUP_CONCAT(seat_no ORDER BY seat_no) FROM booking_seats WHERE booking_id = bk.id) AS seats
     FROM bookings bk
     JOIN schedules s ON s.id = bk.schedule_id
     JOIN routes r    ON r.id = s.route_id
     JOIN buses b     ON b.id = s.bus_id
     WHERE bk.user_id = ?
     ORDER BY s.departure DESC"
);
$stmt->execute([$userId]);
$bookings = $stmt->fetchAll();

$pageTitle = 'My bookings';
require __DIR__ . '/includes/header.php';
?>
<h1>My bookings</h1>

<?php if (!$bookings): ?>
    <p class="muted">No bookings yet. <a href="<?= e(url('index.php')) ?>">Find a bus</a>.</p>
<?php else: ?>
<div class="table-wrap">
<table>
    <thead>
        <tr><th>Trip</th><th>Departure</th><th>Seats</th><th>Amount</th><th>Ref</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($bookings as $bk):
        $labels = $bk['seats'] ? implode(', ', array_map(fn ($n) => seat_label((int) $n), explode(',', $bk['seats']))) : '—';
    ?>
        <tr>
            <td><?= e($bk['source']) ?> → <?= e($bk['destination']) ?><br><span class="muted small"><?= e($bk['bus_name']) ?></span></td>
            <td><?= e(fmt_datetime($bk['departure'])) ?></td>
            <td><?= e($labels) ?> <span class="muted small">(<?= (int) $bk['seat_count'] ?>)</span></td>
            <td><?= money((int) $bk['total_amount']) ?></td>
            <td><code><?= e($bk['payment_ref']) ?></code></td>
            <td>
                <?php if ($bk['status'] === 'cancelled'): ?>
                    <span class="badge badge-red">Cancelled</span>
                <?php elseif ($bk['is_upcoming']): ?>
                    <span class="badge badge-green">Confirmed</span>
                <?php else: ?>
                    <span class="badge">Completed</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($bk['status'] === 'confirmed' && $bk['is_upcoming']): ?>
                    <form method="post" onsubmit="return confirm('Cancel this booking?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="booking_id" value="<?= (int) $bk['id'] ?>">
                        <button class="btn btn-small btn-danger" name="cancel" value="1">Cancel</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>