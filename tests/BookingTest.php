<?php
declare(strict_types=1);

/** SQLite world: one companion (id 2), one active report (id 10), slots relative to $now. */
function booking_world(): array
{
    $pdo = sqlite(
        'CREATE TABLE companion_slots (id INTEGER PRIMARY KEY AUTOINCREMENT, companion_id INTEGER, starts_at TEXT, duration_min INTEGER, mode TEXT, location TEXT, created_at TEXT)',
        "CREATE TABLE bookings (id INTEGER PRIMARY KEY AUTOINCREMENT, slot_id INTEGER, report_id INTEGER, status TEXT DEFAULT 'aktif', cancelled_by TEXT, created_at TEXT, cancelled_at TEXT)"
    );
    Database::setConnection($pdo);
    $now = strtotime('2026-11-20 09:00:00');
    $slot = function (int $companion, string $at) use ($pdo): int {
        $pdo->prepare("INSERT INTO companion_slots (companion_id, starts_at, duration_min, mode, created_at) VALUES (?, ?, 45, 'suara', '2026-11-19')")->execute([$companion, $at]);
        return (int) $pdo->lastInsertId();
    };
    $report = ['id' => 10, 'status' => 'didampingi', 'assigned_to' => 2];
    return [$pdo, $now, $slot, $report];
}

function done_booking(): void
{
    Database::setConnection(null);
}

test('books a free future slot of the assigned companion', function () {
    [$pdo, $now, $slot, $report] = booking_world();
    $id = Booking::book($slot(2, '2026-11-21 10:00:00'), $report, $now);
    assert_true($id > 0);
    assert_same('aktif', $pdo->query('SELECT status FROM bookings')->fetchColumn());
    done_booking();
});

test('the same slot cannot be booked twice (second report gets a clear message)', function () {
    [$pdo, $now, $slot, $report] = booking_world();
    $s = $slot(2, '2026-11-21 10:00:00');
    Booking::book($s, $report, $now);
    assert_throws(DomainException::class, fn () => Booking::book($s, ['id' => 11, 'status' => 'didampingi', 'assigned_to' => 2], $now),
        fn ($e) => assert_same('Slot ini baru saja dipesan orang lain. Pilih waktu lain.', $e->getMessage()));
    assert_same(1, (int) $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn());
    done_booking();
});

test("cannot book another companion's slot", function () {
    [, $now, $slot, $report] = booking_world();
    assert_throws(DomainException::class, fn () => Booking::book($slot(5, '2026-11-21 10:00:00'), $report, $now));
    done_booking();
});

test('cannot book past slots or slots starting within an hour', function () {
    [, $now, $slot, $report] = booking_world();
    assert_throws(DomainException::class, fn () => Booking::book($slot(2, '2026-11-20 08:00:00'), $report, $now));
    assert_throws(DomainException::class, fn () => Booking::book($slot(2, '2026-11-20 09:30:00'), $report, $now));
    done_booking();
});

test('cannot book before a companion is assigned or after the case is closed', function () {
    [, $now, $slot] = booking_world();
    $s = $slot(2, '2026-11-21 10:00:00');
    assert_throws(DomainException::class, fn () => Booking::book($s, ['id' => 10, 'status' => 'ditinjau', 'assigned_to' => null], $now));
    assert_throws(DomainException::class, fn () => Booking::book($s, ['id' => 10, 'status' => 'selesai', 'assigned_to' => 2], $now));
    done_booking();
});

test('at most two upcoming sessions per report', function () {
    [, $now, $slot, $report] = booking_world();
    Booking::book($slot(2, '2026-11-21 10:00:00'), $report, $now);
    Booking::book($slot(2, '2026-11-22 10:00:00'), $report, $now);
    assert_throws(DomainException::class, fn () => Booking::book($slot(2, '2026-11-23 10:00:00'), $report, $now));
    done_booking();
});

test('cancelling frees the slot for someone else', function () {
    [, $now, $slot, $report] = booking_world();
    $s = $slot(2, '2026-11-21 10:00:00');
    $b = Booking::book($s, $report, $now);
    Booking::cancel($b, 'survivor', $now);
    assert_true(Booking::book($s, ['id' => 11, 'status' => 'didampingi', 'assigned_to' => 2], $now) > 0);
    done_booking();
});

test('survivors cannot cancel within 2 hours; companions can until it starts', function () {
    [, $now, $slot, $report] = booking_world();
    $b = Booking::book($slot(2, '2026-11-20 10:30:00'), $report, $now);
    assert_throws(DomainException::class, fn () => Booking::cancel($b, 'survivor', $now));
    Booking::cancel($b, 'companion', $now);
    assert_throws(DomainException::class, fn () => Booking::cancel($b, 'companion', $now), fn ($e) => assert_true(str_contains($e->getMessage(), 'sudah dibatalkan')));
    done_booking();
});

test('slot validation: time window, duration, mode, location', function () {
    $now = strtotime('2026-11-20 09:00:00');
    assert_same(null, Slot::validate('2026-11-21', '10:00', 45, 'suara', '', $now));
    assert_true(Slot::validate('2026-11-20', '09:30', 45, 'suara', '', $now) !== null);
    assert_true(Slot::validate('2027-03-01', '10:00', 45, 'suara', '', $now) !== null);
    assert_true(Slot::validate('2026-11-21', '10:00', 50, 'suara', '', $now) !== null);
    assert_true(Slot::validate('2026-11-21', '10:00', 45, 'telepati', '', $now) !== null);
    assert_same('Tulis lokasi pertemuan tatap muka.', Slot::validate('2026-11-21', '10:00', 45, 'tatap', ' ', $now));
    assert_true(Slot::validate('2026-02-30', '10:00', 45, 'suara', '', $now) !== null);
});

/*
 * Real concurrency on MySQL: while transaction A holds the slot lock, B cannot read the slot row and times out.
 * After A commits its booking, B is refused. Uses a throwaway database haven_v2_test; skipped when MySQL is down.
 */
test('MySQL: SELECT … FOR UPDATE serializes two bookings of the same slot', function () {
    $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', Config::get('DB_HOST'), Config::int('DB_PORT', 3306));
    try {
        $root = new PDO($dsn, (string) Config::get('DB_USER'), (string) Config::get('DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]);
    } catch (PDOException) {
        echo "        (dilewati: MySQL tidak berjalan)\n";
        return;
    }
    $db = 'haven_v2_test';
    SqlFile::run($root, ROOT_PATH . '/database/schema.sql', $db);
    $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
    $a = new PDO("$dsn;dbname=$db", (string) Config::get('DB_USER'), (string) Config::get('DB_PASS'), $opts);
    $b = new PDO("$dsn;dbname=$db", (string) Config::get('DB_USER'), (string) Config::get('DB_PASS'), $opts);
    try {
        $a->exec("INSERT INTO users (id, name, email, password_hash, role, created_at, updated_at) VALUES (2, 'Dewi', 'd@x.id', 'x', 'companion', NOW(), NOW())");
        $a->exec("INSERT INTO reports (id, code, pin_hash, category, status, chronology_enc, assigned_to, created_at, updated_at) VALUES
                  (10, 'HV-AAAAAA', 'x', 'nonfisik', 'didampingi', 'x', 2, NOW(), NOW()), (11, 'HV-BBBBBB', 'x', 'nonfisik', 'didampingi', 'x', 2, NOW(), NOW())");
        $a->exec("INSERT INTO companion_slots (id, companion_id, starts_at, duration_min, mode, created_at) VALUES (1, 2, DATE_ADD(NOW(), INTERVAL 2 DAY), 45, 'suara', NOW())");

        // A: survivor 1 is mid-booking and holds the slot row lock.
        $a->beginTransaction();
        $a->query('SELECT * FROM companion_slots WHERE id = 1 FOR UPDATE')->fetch();

        // B: survivor 2 tries the same slot at the same moment and must wait, then time out.
        $b->exec('SET SESSION innodb_lock_wait_timeout = 1');
        Database::setConnection($b);
        $r11 = ['id' => 11, 'status' => 'didampingi', 'assigned_to' => 2];
        assert_throws(PDOException::class, fn () => Booking::book(1, $r11), fn ($e) => assert_true(str_contains($e->getMessage(), 'Lock wait timeout')));

        // A finishes its booking.
        $a->exec("INSERT INTO bookings (slot_id, report_id, status, created_at) VALUES (1, 10, 'aktif', NOW())");
        $a->commit();

        // B retries and is told the slot is taken.
        assert_throws(DomainException::class, fn () => Booking::book(1, $r11), fn ($e) => assert_true(str_contains($e->getMessage(), 'baru saja dipesan')));
        assert_same(1, (int) $a->query("SELECT COUNT(*) FROM bookings WHERE slot_id = 1 AND status = 'aktif'")->fetchColumn());
    } finally {
        Database::setConnection(null);
        $a = $b = null;
        $root->exec("DROP DATABASE IF EXISTS $db");
    }
});
