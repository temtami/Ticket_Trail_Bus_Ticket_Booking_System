<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$scheduleId = (int) ($_GET['schedule'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT s.*, b.name AS bus_name, b.reg_no, b.total_seats, bt.name AS bus_type,
            r.source, r.destination, (s.departure > NOW()) AS is_upcoming
     FROM schedules s
     JOIN buses b      ON b.id = s.bus_id
     JOIN bus_types bt ON bt.id = b.bus_type_id
     JOIN routes r     ON r.id = s.route_id
     WHERE s.id = ?"
);
$stmt->execute([$scheduleId]);
$trip = $stmt->fetch();

if (!$trip || !$trip['is_upcoming']) {
    flash('error', 'That trip is not available for booking.');
    redirect('index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $total = (int) $trip['total_seats'];
    $seats = array_unique(array_map('intval', (array) ($_POST['seats'] ?? [])));
    $seats = array_values(array_filter($seats, fn ($n) => $n >= 1 && $n <= $total));

    if (!$seats) {
        $error = 'Please select at least one seat.';
    } elseif (count($seats) > MAX_SEATS_PER_BOOKING) {
        $error = 'You can book at most ' . MAX_SEATS_PER_BOOKING . ' seats at once.';
    } else {
        try {
            $pdo->beginTransaction();

            $amount = count($seats) * (int) $trip['fare'];
            // Simulated payment reference. Plug in a real gateway here later.
            $paymentRef = 'PAY-' . strtoupper(bin2hex(random_bytes(4)));

            $pdo->prepare(
                'INSERT INTO bookings (user_id, schedule_id, seat_count, total_amount, payment_ref)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([current_user()['id'], $scheduleId, count($seats), $amount, $paymentRef]);

            $bookingId = (int) $pdo->lastInsertId();
            $insertSeat = $pdo->prepare(
                'INSERT INTO booking_seats (booking_id, schedule_id, seat_no) VALUES (?, ?, ?)'
            );
            foreach ($seats as $seat) {
                $insertSeat->execute([$bookingId, $scheduleId, $seat]);
            }

            $pdo->commit();
            flash('success', "Booking confirmed! Payment ref: $paymentRef");
            redirect('my_bookings.php');
        } catch (PDOException $ex) {
            $pdo->rollBack();
            if ($ex->getCode() === '23000') {
                $error = 'Sorry, one or more of those seats were just booked by someone else. Please pick again.';
            } else {
                throw $ex;
            }
        }
    }
}

$bookedStmt = $pdo->prepare('SELECT seat_no FROM booking_seats WHERE schedule_id = ?');
$bookedStmt->execute([$scheduleId]);
$booked = array_map('intval', $bookedStmt->fetchAll(PDO::FETCH_COLUMN));

$pageTitle = 'Select seats';
require __DIR__ . '/includes/header.php';
?>
<h1><?= e($trip['source']) ?> → <?= e($trip['destination']) ?></h1>
<p class="muted">
    <?= e($trip['bus_name']) ?> (<?= e($trip['reg_no']) ?>) · <?= e($trip['bus_type']) ?> ·
    <?= e(fmt_datetime($trip['departure'])) ?> · <?= money((int) $trip['fare']) ?> per seat
</p>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<form method="post" class="booking-layout">
    <?= csrf_field() ?>
    <div class="card">
        <div class="bus-front">Driver 🛞</div>
        <div class="seat-grid">
            <?php for ($n = 1; $n <= (int) $trip['total_seats']; $n++): $taken = in_array($n, $booked, true); ?>
                <label class="seat <?= $taken ? 'taken' : '' ?>">
                    <input type="checkbox" name="seats[]" value="<?= $n ?>" <?= $taken ? 'disabled' : '' ?>>
                    <span><?= e(seat_label($n)) ?></span>
                </label>
            <?php endfor; ?>
        </div>
        <div class="legend">
            <span><i class="dot"></i> Available</span>
            <span><i class="dot dot-selected"></i> Selected</span>
            <span><i class="dot dot-taken"></i> Booked</span>
        </div>
    </div>

    <aside class="card summary">
        <h3>Summary</h3>
        <p>Seats: <strong id="seat-list">None</strong></p>
        <p>Total: <strong id="total"><?= money(0) ?></strong></p>
        <button class="btn btn-block" type="submit">Pay &amp; confirm</button>
        <p class="muted small">Payment is simulated for this demo.</p>
    </aside>
</form>

<script>
(() => {
    const fare = <?= (int) $trip['fare'] ?>;
    const max = <?= MAX_SEATS_PER_BOOKING ?>;
    const boxes = document.querySelectorAll('.seat input:not([disabled])');
    const update = () => {
        const picked = [...boxes].filter(b => b.checked);
        document.getElementById('seat-list').textContent =
            picked.map(b => b.nextElementSibling.textContent).join(', ') || 'None';
        document.getElementById('total').textContent = '₹' + (picked.length * fare).toLocaleString('en-IN');
        boxes.forEach(b => { if (!b.checked) b.disabled = picked.length >= max; });
    };
    boxes.forEach(b => b.addEventListener('change', update));
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>