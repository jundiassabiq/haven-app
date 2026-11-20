<?php
declare(strict_types=1);

/*
 * Fills a freshly reset database with realistic demo activity for the presentation video:
 * ~65 reports over the last 12 months, messages, notes, referrals, and sessions.
 * Report content is encrypted with this installation's APP_KEY, which is why this is a PHP script and not SQL.
 *
 *   php bin/db-reset.php && php bin/seed-demo.php
 *
 * Fixed demo cases (see docs/demo-script.md):
 *   HV-9B2X6P / PIN 482915  anonymous, "in danger", waiting in the admin queue
 *   HV-7Q4K2M               Rani's account case, accompanied by Dewi, with messages and a session tomorrow
 *   HV-3C7MQK / PIN 517390  kasus anak (perkawinan anak) dilaporkan saksi, menunggu penugasan di Banyumas
 *   HV-2S6V4B / PIN 305174  laporan saksi (KDRT) sudah didampingi, persetujuan korban belum tercatat
 *   HV-8T5RJP / PIN 640281  kasus anak di DI Yogyakarta, wilayah tanpa pendamping terlatih
 *
 * Pendamping terlatih kasus anak datang dari blok "-- AREA A" di database/seed.sql
 * (Dewi dan Nindya, keduanya Banyumas). Laporan contohnya ada di berkas ini, bukan
 * di seed.sql, karena kronologi harus dienkripsi AES-256-GCM dengan APP_KEY.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

if ((int) Database::value('SELECT COUNT(*) FROM reports') > 0 && !in_array('--force', $argv, true)) {
    fwrite(STDERR, "Database sudah berisi laporan. Jalankan php bin/db-reset.php dulu, atau tambahkan --force.\n");
    exit(1);
}
mt_srand(20261120);

$admin = User::find(1);
$companions = array_map(static fn ($id) => User::find($id), [2, 4, 5, 6]);
$rani = User::find(3);

$stories = [
    'nonfisik' => [
        'Atasan sering berkomentar tentang tubuh saya di depan rekan kerja dan menganggapnya candaan. Saya sudah meminta berhenti tapi tidak digubris.',
        'Seseorang di grup angkatan terus mengirim stiker dan pesan bernuansa seksual ke saya. Saya merasa tidak nyaman datang ke kelas.',
        'Saat menunggu bus, sekelompok orang bersiul dan mengomentari pakaian saya setiap hari di halte yang sama.',
    ],
    'ksbe' => [
        'Mantan pasangan mengancam akan menyebarkan foto pribadi saya kalau saya tidak mau kembali kepadanya.',
        'Akun tanpa nama mengirim gambar tidak senonoh berkali-kali dan tahu alamat kos saya.',
        'Foto saya diedit menjadi konten tidak pantas dan disebar di grup kampus.',
    ],
    'fisik' => [
        'Di kereta yang padat, seseorang sengaja meraba saya. Saya membeku dan tidak sempat melihat wajahnya dengan jelas.',
        'Rekan kerja beberapa kali menyentuh pinggang saya saat lembur, padahal saya sudah menghindar.',
    ],
    'pemaksaan' => ['Keluarga memaksa saya menikah dengan orang yang tidak saya kenal untuk melunasi utang.', 'Pasangan memaksa saya melakukan hal yang tidak saya setujui dan mengancam akan pergi.'],
    'eksploitasi' => ['Saya dijanjikan pekerjaan, tetapi kemudian dipaksa melayani tamu dan uangnya diambil.'],
    'belum-yakin' => ['Saya tidak yakin apakah yang terjadi termasuk kekerasan. Dosen pembimbing sering mengajak bertemu berdua di luar kampus.'],
    // Enam kategori baru (Req 2.1). Satu cerita saja sudah cukup untuk kategori
    // yang hanya dipakai laporan tetap di bawah.
    'kdrt' => [
        'Suami saya memukul dan membanting barang setiap kali ia marah. Anak-anak ikut ketakutan setiap mendengar suaranya.',
        'Saya dilarang keluar rumah dan ponsel saya diperiksa setiap malam. Kalau saya menolak, ia mengunci pintu kamar dari luar.',
    ],
    'kekerasan-pacaran' => [
        'Pacar saya marah besar setiap kali saya bertemu teman, lalu mencengkeram tangan saya sampai membiru.',
        'Mantan pacar menunggu di depan kampus hampir setiap hari dan mengancam menyakiti dirinya kalau saya tidak kembali.',
    ],
    'psikis' => [
        'Atasan saya berteriak dan merendahkan saya di depan tim hampir setiap rapat. Saya jadi sulit tidur dan takut masuk kerja.',
        'Keluarga saya terus mengatakan saya tidak berguna dan mengancam mengusir saya dari rumah kalau saya membantah.',
    ],
    'ekonomi' => [
        'Gaji saya diambil pasangan setiap bulan dan saya harus meminta uang untuk kebutuhan dasar anak.',
        'Pasangan saya memakai nama saya untuk berutang, dan sekarang penagih datang ke tempat kerja saya.',
    ],
    'perkawinan-anak' => ['Keponakan saya yang masih kelas dua SMP akan dinikahkan bulan depan karena desakan keluarga besar.'],
    'perdagangan-orang' => [
        'Saya direkrut untuk bekerja di luar kota, tetapi dokumen saya ditahan dan saya tidak boleh pulang.',
        'Teman saya dibawa ke kota lain dengan janji kerja di restoran, lalu dipaksa bekerja tanpa dibayar dan dikurung.',
    ],
];
// Jumlah laporan historis per kategori. Enam kategori baru ikut di sini supaya
// filter kategori di antrean admin dan statistik punya data (Req 2.1, 2.8).
// Tiga kategori diberi lima laporan agar lolos ambang sel kecil Aggregator;
// sisanya sengaja di bawah lima supaya penggabungan ke "Lainnya" juga terlihat.
//
// `perkawinan-anak` tidak ada di daftar ini. Kategori itu selalu menjadi kasus
// anak (ReportIntake::derive), dan kasus anak hanya boleh ditugaskan ke
// pendamping terlatih di wilayahnya (Req 4.9). Riwayat acak akan menugaskannya
// ke pendamping mana saja, jadi contohnya dibuat sebagai laporan tetap di bawah
// yang masih menunggu di antrean.
$plan = [
    'nonfisik' => 15, 'ksbe' => 11, 'fisik' => 9, 'pemaksaan' => 5, 'eksploitasi' => 2, 'belum-yakin' => 3,
    'kdrt' => 5, 'kekerasan-pacaran' => 5, 'psikis' => 5, 'ekonomi' => 3, 'perdagangan-orang' => 2,
];
$cities = [['Jawa Tengah', 'Banyumas'], ['Jawa Tengah', 'Banyumas'], ['Jawa Tengah', 'Purbalingga'], ['Jawa Tengah', 'Cilacap'], ['Jawa Tengah', 'Semarang'], ['DI Yogyakarta', 'Yogyakarta']];
$companionLines = ['Halo, terima kasih sudah percaya bercerita. Aku pendampingmu. Apakah sekarang kamu berada di tempat yang aman?', 'Pelan-pelan saja, tidak ada yang perlu dijelaskan ulang kalau belum siap.', 'Aku sudah membuka beberapa jadwal kalau kamu ingin bicara lewat panggilan.', 'Kalau kamu setuju, aku bisa bantu hubungkan dengan layanan psikologis di wilayahmu.'];
$survivorLines = ['Iya, aku aman sekarang. Terima kasih sudah membalas.', 'Aku masih bingung harus mulai dari mana.', 'Boleh, aku mau coba sesi minggu ini.', 'Terima kasih, aku merasa lebih tenang setelah bercerita.'];
$services = array_column(Database::all("SELECT id, city FROM services WHERE type IN ('uptd','psikolog','lbh','rs')"), 'id');

$at = static fn (int $ts) => date('Y-m-d H:i:s', $ts);
$created = 0;

/** Creates one report and walks it to $final, backdating every timestamp from $t0. */
$make = static function (string $cat, int $t0, string $final, ?int $userId, ?array $companion, array $extra = []) use ($stories, $cities, $admin, $companionLines, $survivorLines, $services, $at): array {
    [$prov, $city] = $cities[mt_rand(0, count($cities) - 1)];
    $story = $stories[$cat][mt_rand(0, count($stories[$cat]) - 1)];
    $in = $extra + [
        'jenis' => $cat, 'bahaya' => mt_rand(0, 9) === 0 ? 'ya' : 'tidak', 'kronologi' => $story, 'berlangsung' => ['ya', 'tidak', 'tidak-tahu'][mt_rand(0, 2)],
        'tanggal' => date('Y-m-d', $t0 - mt_rand(1, 20) * 86400), 'tempat' => Report::PLACES[mt_rand(0, count(Report::PLACES) - 1)], 'provinsi' => $prov, 'kota' => $city,
        'pelaku' => Report::RELATIONS[mt_rand(0, 6)], 'identitas' => $userId ? 'akun' : 'anonim', 'kontak_email' => '', 'email' => '', 'kontak_wa' => '', 'wa' => '',
        // Pilihan wajib di form lapor (Req 4.1, 5.1). Riwayat memakai jawaban
        // paling umum: korban dewasa yang melapor sendiri. Laporan saksi dan
        // kasus anak mengganti nilai ini lewat $extra.
        'usia' => 'tidak', 'peran' => 'korban', 'kontak_korban' => '',
    ];
    $c = Report::create($in, $userId, []);
    $id = $c['id'];
    $r = Report::find($id);
    $times = ['diterima' => $t0];
    if ($final !== 'diterima') {
        Auth::actAs($admin);
        $r = CaseWorkflow::transition($r, 'ditinjau');
        $times['ditinjau'] = $t0 + mt_rand(1, 6) * 3600;
        if ($final === 'ditutup') {
            $r = CaseWorkflow::transition($r, 'ditutup', ['reason' => 'Laporan duplikat dari laporan sebelumnya dengan kronologi yang sama.']);
            $times['ditutup'] = $times['ditinjau'] + 3600;
        } else {
            $r = CaseWorkflow::transition($r, 'didampingi', ['companion_id' => (int) $companion['id']]);
            $times['didampingi'] = $times['ditinjau'] + mt_rand(1, 3) * 3600;
            Auth::actAs($companion);
            $first = min($times['didampingi'] + mt_rand(1, 8) * 3600, time() - 3600);
            $n = mt_rand(2, 4);
            for ($i = 0; $i < $n; $i++) {
                Message::create($id, 'companion', (int) $companion['id'], $companionLines[$i % count($companionLines)]);
                Message::create($id, 'survivor', $userId, $survivorLines[$i % count($survivorLines)]);
            }
            CaseNote::add($id, (int) $companion['id'], 'Kontak pertama', 'Penyintas dalam keadaan aman. Sepakat untuk sesi lanjutan.');
            if (in_array($final, ['dirujuk', 'selesai'], true) && ($final === 'dirujuk' || mt_rand(0, 2) === 0)) {
                $r = CaseWorkflow::transition($r, 'dirujuk', ['service_id' => $services[mt_rand(0, count($services) - 1)], 'note' => 'Kami hubungkan dengan layanan lanjutan sesuai persetujuanmu.']);
                $times['dirujuk'] = $first + mt_rand(5, 12) * 86400;
            }
            if ($final === 'selesai') {
                $r = CaseWorkflow::transition($r, 'selesai', ['note' => 'Terima kasih sudah berjalan bersama. Kamu bisa kembali kapan saja.']);
                $times['selesai'] = max($first, $times['dirujuk'] ?? $first) + mt_rand(5, 25) * 86400;
            }
            // Backdate messages between first contact and the last step.
            $end = min(time() - 600, max($times) + 86400);
            $msgs = Database::all('SELECT id FROM messages WHERE report_id = ? ORDER BY id', [$id]);
            $step = min(86400, max(60, (int) (($end - $first) / max(1, count($msgs)))));
            foreach ($msgs as $k => $m) {
                Database::run('UPDATE messages SET created_at = ?, read_at = ? WHERE id = ?', [$at($first + $k * $step), $at($first + $k * $step + 600), $m['id']]);
            }
        }
    }
    foreach ($times as $status => $ts) {
        $times[$status] = min($ts, time() - 300);
        Database::run('UPDATE report_status_logs SET created_at = ? WHERE report_id = ? AND to_status = ?', [$at($times[$status]), $id, $status]);
    }
    Database::run('UPDATE reports SET created_at = ?, updated_at = ? WHERE id = ?', [$at($t0), $at(max($times)), $id]);
    Database::run('UPDATE case_notes SET created_at = ? WHERE report_id = ?', [$at(($times['didampingi'] ?? $t0) + 7200), $id]);
    Auth::actAs(null);
    return Report::find($id);
};

// ---------- Historical cases: more recent months are busier ----------
$queue = [];
foreach ($plan as $cat => $n) {
    for ($i = 0; $i < $n; $i++) {
        $queue[] = $cat;
    }
}
shuffle($queue);
$total = count($queue);
foreach ($queue as $i => $cat) {
    $ageDays = (int) round(((1 - ($i / $total)) ** 1.4) * 355) + 4; // spread over ~12 months, denser recently
    $t0 = time() - $ageDays * 86400 - mt_rand(0, 20) * 3600;
    $final = match (true) {
        $ageDays < 30 => ['didampingi', 'didampingi', 'dirujuk'][mt_rand(0, 2)],
        $ageDays < 70 => ['didampingi', 'dirujuk', 'selesai'][mt_rand(0, 2)],
        $i % 17 === 0 => 'ditutup',
        default => 'selesai',
    };
    $make($cat, $t0, $final, null, $companions[$i % count($companions)]);
    $created++;
}
// Two fresh reports waiting for triage.
foreach (['nonfisik', 'ksbe'] as $k => $cat) {
    $make($cat, time() - (5 + $k * 20) * 3600, 'diterima', null, null);
    $created++;
}

// Completed sessions for the public "sessions completed" number.
foreach (Database::all("SELECT id, assigned_to, created_at FROM reports WHERE status IN ('dirujuk','selesai') AND assigned_to IS NOT NULL") as $k => $r) {
    $ts = strtotime($r['created_at']) + (3 + $k % 5) * 86400 + 10 * 3600;
    if ($ts > time() - 86400) {
        continue;
    }
    $slot = Slot::create((int) $r['assigned_to'], $at($ts), 45, 'suara', null);
    Database::run("INSERT INTO bookings (slot_id, report_id, status, created_at) VALUES (?, ?, 'aktif', ?)", [$slot, $r['id'], $at($ts - 2 * 86400)]);
}

// Older notifications are history; mark them read so the demo starts with a clean bell.
Database::run('UPDATE notifications SET read_at = created_at');

// ---------- Fixed demo case 1: Rani's account case with Dewi ----------
$dewi = $companions[0];
$rCase = $make('nonfisik', time() - 8 * 86400, 'didampingi', (int) $rani['id'], $dewi, [
    'kronologi' => "Seorang teman satu angkatan sering mengirim pesan dan komentar bernuansa seksual di grup kelas dan lewat pesan pribadi. Saya sudah meminta berhenti, tapi ia menganggapnya bercanda dan teman lain ikut tertawa.\n\nSaya jadi enggan datang ke kelas yang sama dengannya dan susah tidur. Saya belum tahu apakah ingin melapor ke kampus.",
    'provinsi' => 'Jawa Tengah', 'kota' => 'Banyumas', 'tempat' => 'Kampus atau sekolah', 'pelaku' => 'Teman kuliah atau rekan kerja', 'berlangsung' => 'ya', 'bahaya' => 'tidak',
    'kontak_email' => '1', 'email' => 'rani.aman@contoh.id',
]);
Database::run('UPDATE reports SET code = ? WHERE id = ?', ['HV-7Q4K2M', $rCase['id']]);
Database::run('UPDATE notifications SET link = REPLACE(link, ?, ?) WHERE report_id = ?', [$rCase['code'], 'HV-7Q4K2M', $rCase['id']]);
Message::create((int) $rCase['id'], 'companion', (int) $dewi['id'], 'Itu wajar sekali setelah mengalami hal seperti ini. Aku sudah membuka beberapa jadwal minggu ini kalau kamu mau bicara lewat panggilan. Tidak ada paksaan ya.');
Notifier::survivor(Report::find((int) $rCase['id']), 'pesan', 'Pesan baru dari pendamping', '#pesan');
$tomorrow = date('Y-m-d', time() + 86400);
$booked = Slot::create((int) $dewi['id'], "$tomorrow 10:00:00", 45, 'suara', null);
Slot::create((int) $dewi['id'], "$tomorrow 15:30:00", 45, 'video', null);
Slot::create((int) $dewi['id'], date('Y-m-d', time() + 3 * 86400) . ' 09:00:00', 60, 'tatap', 'Ruang konseling UPTD PPA Banyumas');
Slot::create((int) $dewi['id'], date('Y-m-d', time() + 3 * 86400) . ' 19:00:00', 45, 'suara', null);
Booking::book($booked, Report::find((int) $rCase['id']));
$created++;

// ---------- Fixed demo case 2: anonymous, in danger, waiting ----------
$aCase = $make('fisik', time() - 25 * 60, 'diterima', null, null, [
    'bahaya' => 'ya', 'berlangsung' => 'ya', 'provinsi' => 'Jawa Tengah', 'kota' => 'Banyumas', 'tempat' => 'Tempat kerja', 'pelaku' => 'Teman kuliah atau rekan kerja',
    'kronologi' => 'Rekan kerja beberapa kali menyentuh saya tanpa izin saat lembur, dan hari ini ia menunggu di depan kos. Saya takut pulang sendirian.',
    'kontak_wa' => '1', 'wa' => '0812-0000-0000',
]);
Database::run('UPDATE reports SET code = ?, pin_hash = ?, urgency = ? WHERE id = ?', ['HV-9B2X6P', password_hash('482915', PASSWORD_DEFAULT), 'tinggi', $aCase['id']]);
Notifier::admins((int) $aCase['id'], 'laporan', 'Laporan baru: HV-9B2X6P (menyatakan dalam bahaya)', '/admin/kasus/HV-9B2X6P');
$created++;

// ---------- Fixed demo case 3 (Area A): kasus anak dilaporkan saksi ----------
// Kategori baru `perkawinan-anak` (Req 2.1), jawaban usia "ya" (Req 4.3), peran
// saksi (Req 5.2), dan kontak korban yang dititipkan saksi — tersimpan
// terenkripsi oleh Report::create, tidak pernah plaintext (Req 5.9).
// Dibiarkan di status "diterima" supaya penugasan pendamping terlatih bisa
// diperagakan langsung dari antrean admin: wilayahnya Banyumas, dan di sana ada
// Dewi serta Nindya yang ditandai terlatih oleh blok AREA A di seed.sql
// (Req 4.9). Lencana "Anak" dan label "Dilaporkan oleh saksi" muncul di
// antrean karena kolomnya, bukan karena data khusus di sini.
$childCase = $make('perkawinan-anak', time() - 3 * 3600, 'diterima', null, null, [
    'usia' => 'ya', 'peran' => 'saksi', 'bahaya' => 'tidak', 'berlangsung' => 'ya',
    'provinsi' => 'Jawa Tengah', 'kota' => 'Banyumas', 'tempat' => 'Rumah atau kos', 'pelaku' => 'Anggota keluarga',
    'kronologi' => "Saya guru di sekolah tempat anak ini bersekolah. Dua minggu terakhir ia sering tidak masuk, dan hari ini ia bercerita bahwa keluarganya sudah menentukan tanggal pernikahannya bulan depan. Usianya 14 tahun dan ia kelas dua SMP.\n\nIa minta tolong tetapi takut keluarganya tahu bahwa ia bercerita. Saya melapor atas namanya karena ia belum berani melapor sendiri.",
    'kontak_korban' => 'Hubungi lewat sekolah, bukan ke rumah. Wali kelas Bu Ratih, 0812-5555-1234.',
]);
Database::run('UPDATE reports SET code = ?, pin_hash = ? WHERE id = ?', ['HV-3C7MQK', password_hash('517390', PASSWORD_DEFAULT), $childCase['id']]);
// Req 4.11: kode, jenis peristiwa, dan waktu saja — kronologi tidak masuk audit.
Audit::log('report.child_case', 'report', 'HV-3C7MQK', 'Child_Case created · urgensi tinggi', null, 'Anonim');
Notifier::admins((int) $childCase['id'], 'laporan', 'Laporan baru: HV-3C7MQK (kasus anak, dilaporkan saksi)', '/admin/kasus/HV-3C7MQK');
$created++;

// ---------- Fixed demo case 4 (Area A): laporan saksi menunggu persetujuan korban ----------
// Sudah didampingi tetapi belum ada baris di report_consents, jadi pendamping
// bisa mencoba mengubah status menjadi "dirujuk" dan melihat penolakan
// ConsentGuard (Req 5.8), lalu mencatat persetujuan dan mencoba lagi (Req 5.7).
// Statusnya dijalankan manual, bukan lewat jalur $make, supaya tidak ada pesan
// bersuara penyintas di kasus yang pelapornya saksi.
$sari = $companions[1];
$wT0 = time() - 6 * 86400;
$wCase = $make('kdrt', $wT0, 'diterima', null, null, [
    'usia' => 'tidak', 'peran' => 'saksi', 'bahaya' => 'tidak', 'berlangsung' => 'ya',
    'provinsi' => 'Jawa Tengah', 'kota' => 'Purbalingga', 'tempat' => 'Rumah atau kos', 'pelaku' => 'Pasangan atau mantan pasangan',
    'kronologi' => "Saya tetangga korban. Hampir setiap malam saya mendengar bentakan dan suara barang dibanting dari rumah sebelah, dan beberapa kali ia datang ke rumah saya dengan lebam di tangan.\n\nIa belum berani melapor karena takut suaminya tahu. Saya melapor supaya ada pendamping yang bisa menemaninya kalau ia siap.",
    'kontak_korban' => 'Nomor saya, bukan nomor korban: 0813-7777-2468. Korban bisa dititipi pesan lewat saya saat siang.',
]);
$wId = (int) $wCase['id'];
Database::run('UPDATE reports SET code = ?, pin_hash = ? WHERE id = ?', ['HV-2S6V4B', password_hash('305174', PASSWORD_DEFAULT), $wId]);
Auth::actAs($admin);
$wCase = CaseWorkflow::transition(Report::find($wId), 'ditinjau');
$wCase = CaseWorkflow::transition($wCase, 'didampingi', ['companion_id' => (int) $sari['id']]);
Auth::actAs($sari);
CaseNote::add($wId, (int) $sari['id'], 'Kontak pertama', 'Pelapor adalah tetangga korban. Persetujuan korban belum ada, jadi rujukan belum bisa diajukan.');
Message::create($wId, 'companion', (int) $sari['id'], 'Terima kasih sudah melapor. Sebelum kami hubungkan ke layanan lain, kami perlu persetujuan dari korban lebih dulu. Kalau memungkinkan, boleh tanyakan apakah ia bersedia dihubungi pendamping?');
Auth::actAs(null);
// Backdate: status log, catatan, dan pesan mengikuti waktu laporan, bukan waktu seed dijalankan.
foreach (['ditinjau' => 5, 'didampingi' => 7] as $status => $hours) {
    Database::run('UPDATE report_status_logs SET created_at = ? WHERE report_id = ? AND to_status = ?', [$at($wT0 + $hours * 3600), $wId, $status]);
}
Database::run('UPDATE messages SET created_at = ? WHERE report_id = ?', [$at($wT0 + 8 * 3600), $wId]);
Database::run('UPDATE case_notes SET created_at = ? WHERE report_id = ?', [$at($wT0 + 8 * 3600), $wId]);
Database::run('UPDATE reports SET created_at = ?, updated_at = ? WHERE id = ?', [$at($wT0), $at($wT0 + 8 * 3600), $wId]);
$created++;

// ---------- Fixed demo case 5 (Area A): kasus anak di wilayah tanpa pendamping terlatih ----------
// Jawaban usia "tidak-yakin" tetap menjadikannya kasus anak (Req 4.3), dan
// wilayahnya DI Yogyakarta yang tidak punya pendamping sama sekali di seed.
// Admin yang menugaskan di sini melihat peringatan "tidak ada pendamping
// terlatih" dan harus mengonfirmasi penugasannya (Req 4.10).
$childElsewhere = $make('kekerasan-pacaran', time() - 9 * 3600, 'diterima', null, null, [
    'usia' => 'tidak-yakin', 'peran' => 'korban', 'bahaya' => 'tidak', 'berlangsung' => 'ya',
    'provinsi' => 'DI Yogyakarta', 'kota' => 'Yogyakarta', 'tempat' => 'Ruang daring atau media sosial', 'pelaku' => 'Pasangan atau mantan pasangan',
    'kronologi' => "Pacar saya memeriksa ponsel saya setiap hari dan marah kalau saya membalas pesan teman. Minggu lalu ia mengancam menyebarkan foto saya kalau saya memblokirnya.\n\nSaya masih sekolah dan belum tahu harus bercerita ke siapa. Saya tidak yakin umur saya masih dihitung anak atau tidak.",
]);
Database::run('UPDATE reports SET code = ?, pin_hash = ? WHERE id = ?', ['HV-8T5RJP', password_hash('640281', PASSWORD_DEFAULT), $childElsewhere['id']]);
Audit::log('report.child_case', 'report', 'HV-8T5RJP', 'Child_Case created · urgensi tinggi', null, 'Anonim');
Notifier::admins((int) $childElsewhere['id'], 'laporan', 'Laporan baru: HV-8T5RJP (kasus anak)', '/admin/kasus/HV-8T5RJP');
$created++;

echo "Data demo dibuat: $created laporan, " . Database::value('SELECT COUNT(*) FROM messages') . ' pesan, '
    . Database::value('SELECT COUNT(*) FROM bookings') . " sesi.\n";
echo "  HV-9B2X6P / PIN 482915  anonim, dalam bahaya, menunggu di antrean admin\n";
echo "  HV-7Q4K2M               laporan berakun Rani (rani@demo.id), didampingi Dewi, sesi besok 10.00\n";
echo "  HV-3C7MQK / PIN 517390  kasus anak dilaporkan saksi, Banyumas, menunggu penugasan pendamping terlatih\n";
echo "  HV-2S6V4B / PIN 305174  laporan saksi, didampingi Sari, persetujuan korban belum tercatat\n";
echo "  HV-8T5RJP / PIN 640281  kasus anak di DI Yogyakarta, wilayah tanpa pendamping terlatih\n";
