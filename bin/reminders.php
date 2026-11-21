<?php
declare(strict_types=1);

/*
 * Pengingat kasus mendesak (Req 6.3 sampai 6.7, 6.15, 6.16).
 *   php bin/reminders.php
 *
 * Dijadwalkan tiap 5 sampai 15 menit lewat Windows Task Scheduler atau cron,
 * tanpa daemon. Keputusan siapa diingatkan ada di ReminderPlanner (murni);
 * berkas ini hanya membaca kandidat, menulis penanda, dan mengirim notifikasi.
 *
 * Seluruh run berjalan dalam satu transaksi. Penanda ditulis dengan
 * INSERT IGNORE ke report_reminders yang UNIQUE (report_id, type, period),
 * dan notifikasi hanya dikirim saat baris penandanya benar-benar baru, sehingga
 * dua run berdekatan tidak menambah baris maupun notifikasi (Req 6.6).
 *
 * Keluar 0 bila run selesai tanpa galat (Req 6.7). Bila database tidak
 * tersedia atau ada galat lain: transaksi dibatalkan sehingga tidak ada
 * pengingat yang dibuat, kegagalan ditulis ke log aplikasi, dan keluar 1
 * (Req 6.16).
 *
 * Pengingat dikirim sebagai notifikasi dalam aplikasi saja, jadi penyintas
 * anonim tanpa email tidak pernah dibuatkan email (Req 6.15). Judul notifikasi
 * hanya memuat jenis pengingat, batas waktu, dan kode laporan — tanpa
 * kronologi dan tanpa isi pesan (Req 10.2).
 */

require dirname(__DIR__) . '/app/bootstrap.php';

/**
 * Kasus yang masih menunggu tindak lanjut, dengan kolom turunan yang dibaca
 * ReminderPlanner. `last_reply_at` hanya dari pesan `companion`: pesan `system`
 * bukan balasan pendamping.
 *
 * @return list<array<string,mixed>>
 */
function reminder_candidates(): array
{
    return Database::all(
        "SELECT r.id, r.code, r.in_danger, r.status, r.assigned_to, r.created_at,
           (SELECT MAX(m.created_at) FROM messages m WHERE m.report_id = r.id AND m.sender_role = 'survivor')  AS last_survivor_at,
           (SELECT MAX(m.created_at) FROM messages m WHERE m.report_id = r.id AND m.sender_role = 'companion') AS last_reply_at
         FROM reports r
         WHERE r.status NOT IN ('selesai','ditutup')
         ORDER BY r.id"
    );
}

/**
 * Kunci pengingat yang sudah pernah dibuat untuk kasus-kasus ini.
 *
 * @param list<int> $reportIds
 * @return list<string>
 */
function reminder_sent_keys(array $reportIds): array
{
    if ($reportIds === []) {
        return [];
    }
    $rows = Database::all(
        'SELECT report_id, type, period FROM report_reminders WHERE report_id IN ('
            . implode(',', array_fill(0, count($reportIds), '?')) . ')',
        $reportIds
    );

    return array_map(
        static fn (array $r): string => ReminderPlanner::key((int) $r['report_id'], (string) $r['type'], (int) $r['period']),
        $rows
    );
}

/** @return list<int> */
function reminder_admin_ids(): array
{
    return array_map(
        static fn (array $r): int => (int) $r['id'],
        Database::all("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id")
    );
}

$now = new DateTimeImmutable('now');
$env = [
    Sla::ENV_DANGER => Config::get(Sla::ENV_DANGER),
    Sla::ENV_DEFAULT => Config::get(Sla::ENV_DEFAULT),
];
$pdo = null;

try {
    $pdo = Database::pdo();
    $pdo->beginTransaction();

    $cases = reminder_candidates();
    $byId = [];
    foreach ($cases as $case) {
        $byId[(int) $case['id']] = $case;
    }

    $plan = ReminderPlanner::plan($cases, reminder_sent_keys(array_keys($byId)), $now, $env, reminder_admin_ids());

    $created = 0;
    $notified = 0;
    foreach ($plan as $item) {
        $case = $byId[$item['case_id']] ?? null;
        if ($case === null) {
            continue;
        }

        $isNew = Database::run(
            'INSERT IGNORE INTO report_reminders (report_id, type, period, created_at) VALUES (?, ?, ?, ?)',
            [$item['case_id'], $item['type'], $item['period'], now()]
        )->rowCount() === 1;

        if (!$isNew) {
            // Run lain sudah membuat pengingat untuk periode ini (Req 6.6).
            continue;
        }
        $created++;

        $title = ReminderPlanner::LABELS[$item['type']] . ' lebih dari ' . sla_duration($item['sla_hours'])
            . ' (' . $case['code'] . ')';
        $companionId = $case['assigned_to'] === null ? null : (int) $case['assigned_to'];

        foreach ($item['recipients'] as $userId) {
            $path = $userId === $companionId ? '/pendamping/kasus/' : '/admin/kasus/';
            Notifier::send($userId, $item['case_id'], 'pengingat', $title, $path . $case['code']);
            $notified++;
        }
    }

    $pdo->commit();
    echo count($cases) . " kasus diperiksa, $created pengingat dibuat, $notified notifikasi dikirim.\n";
    exit(0);
} catch (Throwable $e) {
    try {
        if ($pdo !== null && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Throwable) {
        // Koneksi sudah mati: transaksi yang tidak di-commit tetap dibatalkan server.
    }
    Logger::error('reminders: run dibatalkan, tidak ada pengingat dibuat. ' . $e->getMessage());
    fwrite(STDERR, 'Pengingat gagal: ' . $e->getMessage() . "\n");
    exit(1);
}
