<?php
declare(strict_types=1);

/** Modules 2 to 4: reports. chronology_enc and contact_enc are always encrypted. */
final class Report
{
    /** Keys and their order match the enum reports.category in database/schema.sql exactly. */
    public const CATEGORIES = [
        'nonfisik' => 'Pelecehan nonfisik',
        'fisik' => 'Pelecehan fisik',
        'ksbe' => 'Kekerasan berbasis elektronik',
        'pemaksaan' => 'Pemaksaan',
        'eksploitasi' => 'Eksploitasi seksual',
        'belum-yakin' => 'Belum yakin',
        'kdrt' => 'Kekerasan dalam rumah tangga',
        'kekerasan-pacaran' => 'Kekerasan dalam pacaran',
        'psikis' => 'Kekerasan psikis',
        'ekonomi' => 'Kekerasan ekonomi',
        'perkawinan-anak' => 'Perkawinan anak',
        'perdagangan-orang' => 'Perdagangan orang',
    ];
    public const CATEGORY_DESC = [
        'nonfisik' => 'Komentar, siulan, isyarat, atau pesan bernuansa seksual',
        'fisik' => 'Sentuhan atau kontak fisik tanpa persetujuan',
        'ksbe' => 'Penyebaran atau ancaman menyebar konten intim, penguntitan daring',
        'pemaksaan' => 'Dipaksa melakukan aktivitas seksual, menikah, atau memakai kontrasepsi',
        'eksploitasi' => 'Dimanfaatkan secara seksual demi keuntungan orang lain',
        'belum-yakin' => 'Tidak ada pilihan yang cocok, atau kamu ragu',
        'kdrt' => 'Kekerasan oleh pasangan atau anggota keluarga di dalam rumah',
        'kekerasan-pacaran' => 'Kekerasan, pemaksaan, atau kontrol berlebih dari pacar atau mantan pacar',
        'psikis' => 'Ancaman, penghinaan, atau tekanan yang melukai perasaan dan rasa amanmu',
        'ekonomi' => 'Penghasilan atau hartamu dikuasai, ditahan, atau dipakai tanpa izin',
        'perkawinan-anak' => 'Anak di bawah 18 tahun dipaksa atau dinikahkan',
        'perdagangan-orang' => 'Direkrut, dipindahkan, atau ditahan untuk dieksploitasi',
    ];
    public const STATUS = [
        'diterima' => 'Diterima', 'ditinjau' => 'Ditinjau', 'didampingi' => 'Didampingi',
        'dirujuk' => 'Dirujuk', 'selesai' => 'Selesai', 'ditutup' => 'Ditutup',
    ];
    public const STATUS_HELP = [
        'diterima' => 'Laporan masuk ke antrean admin. Kronologinya sudah dienkripsi.',
        'ditinjau' => 'Admin melihat jenis kejadian dan wilayah untuk memilih pendamping.',
        'didampingi' => 'Pendamping ditugaskan. Pesannya akan muncul di tab Pesan.',
        'dirujuk' => 'Jika kamu setuju, pendamping memilihkan lembaga untuk langkah berikutnya.',
        'selesai' => 'Pendampingan ditutup setelah kamu dan pendamping sepakat. Riwayatnya tetap bisa dibaca.',
        'ditutup' => 'Admin menutup laporan ini tanpa pendampingan. Alasannya tertulis di bawah.',
    ];
    public const URGENCY = ['tinggi' => 'Tinggi', 'sedang' => 'Sedang', 'rendah' => 'Rendah'];
    public const ONGOING = ['ya' => 'Ya, masih terjadi', 'tidak' => 'Tidak, sudah berhenti', 'tidak-tahu' => 'Tidak yakin'];
    public const PLACES = ['Kampus atau sekolah', 'Tempat kerja', 'Rumah atau kos', 'Transportasi umum', 'Ruang daring atau media sosial', 'Tempat umum lain'];
    public const RELATIONS = ['Tidak kenal', 'Teman atau kenalan', 'Pasangan atau mantan pasangan', 'Anggota keluarga', 'Teman kuliah atau rekan kerja', 'Dosen, guru, atau atasan', 'Lainnya', 'Tidak ingin menyebutkan'];
    public const PROVINCES = [
        'Aceh', 'Sumatera Utara', 'Sumatera Barat', 'Riau', 'Kepulauan Riau', 'Jambi', 'Sumatera Selatan', 'Kepulauan Bangka Belitung',
        'Bengkulu', 'Lampung', 'DKI Jakarta', 'Jawa Barat', 'Banten', 'Jawa Tengah', 'DI Yogyakarta', 'Jawa Timur', 'Bali',
        'Nusa Tenggara Barat', 'Nusa Tenggara Timur', 'Kalimantan Barat', 'Kalimantan Tengah', 'Kalimantan Selatan', 'Kalimantan Timur',
        'Kalimantan Utara', 'Sulawesi Utara', 'Gorontalo', 'Sulawesi Tengah', 'Sulawesi Barat', 'Sulawesi Selatan', 'Sulawesi Tenggara',
        'Maluku', 'Maluku Utara', 'Papua', 'Papua Barat', 'Papua Barat Daya', 'Papua Tengah', 'Papua Pegunungan', 'Papua Selatan',
    ];
    /** Steps shown on the survivor timeline (ditutup replaces the tail when it happens). */
    public const TIMELINE = ['diterima', 'ditinjau', 'didampingi', 'dirujuk', 'selesai'];

    /** Batas panjang kontak korban yang ditulis saksi (Req 5.9). */
    public const VICTIM_CONTACT_MAX = 255;

    /**
     * Pilihan filter kasus anak di antrean admin (Req 4.8). Kunci adalah nilai
     * `?anak=` yang diterima; nilai lain diabaikan dan antrean tidak disaring.
     */
    public const CHILD_FILTER = ['1' => 'Kasus anak', '0' => 'Bukan kasus anak'];

    /**
     * All categories for pickers and filters, with the catch-all "Belum yakin" last.
     * CATEGORIES itself keeps the enum order, so this only changes what the reader sees.
     * @return array<string,string> slug => label
     */
    public static function categoryChoices(): array
    {
        $out = self::CATEGORIES;
        $fallback = $out['belum-yakin'];
        unset($out['belum-yakin']);
        return $out + ['belum-yakin' => $fallback];
    }

    /**
     * Server-side validation of the 4-step form.
     *
     * Pilihan wajib di awal form (jenis, usia korban, peran pelapor) diperiksa
     * `ReportIntake::intakeErrors`; pemanggil menggabungkan kedua daftar.
     *
     * @return array<string,string> field => message
     */
    public static function validationErrors(array $in, bool $accountAllowed): array
    {
        $errors = Validator::make($in, [
            'jenis' => 'required|in:' . implode(',', array_keys(self::CATEGORIES)),
            'bahaya' => 'required|in:ya,tidak',
            'kronologi' => 'required|min:30|max:5000',
            'berlangsung' => 'required|in:' . implode(',', array_keys(self::ONGOING)),
            'tanggal' => 'date',
            'tempat' => ['in:' . implode(',', self::PLACES)],
            'provinsi' => ['in:' . implode(',', self::PROVINCES)],
            'kota' => 'max:80',
            'pelaku' => ['in:' . implode(',', self::RELATIONS)],
            'identitas' => 'required|in:anonim,akun',
            'email' => 'email',
            'wa' => ['regex:/^[0-9+\- ]{9,16}$/'],
            'kontak_korban' => 'max:' . self::VICTIM_CONTACT_MAX,
            'setuju' => 'accepted',
        ], [
            'jenis.required' => 'Pilih jenis kejadian, atau pilih "Belum yakin".',
            'jenis.in' => 'Jenis kejadian itu tidak ada dalam daftar. Pilih salah satu pilihan di atas.',
            'bahaya.required' => 'Pilih salah satu.',
            'kronologi.required' => 'Ceritakan sedikit tentang kejadiannya, minimal satu atau dua kalimat.',
            'kronologi.min' => 'Tulis minimal 30 karakter agar pendamping bisa memahami kejadiannya.',
            'berlangsung.required' => 'Pilih salah satu.',
            'wa' => 'Tulis nomor 9 sampai 16 digit, contoh 0812-3456-7890.',
            'kontak_korban' => 'Tulis kontak korban paling banyak ' . self::VICTIM_CONTACT_MAX . ' karakter, atau kosongkan.',
            'setuju.accepted' => 'Centang ini agar kami bisa memproses laporanmu.',
        ]);
        if (!isset($errors['tanggal']) && ($in['tanggal'] ?? '') !== '' && $in['tanggal'] > date('Y-m-d')) {
            $errors['tanggal'] = 'Tanggal tidak boleh setelah hari ini.';
        }
        if (!empty($in['kontak_email']) && trim((string) ($in['email'] ?? '')) === '') {
            $errors['email'] = 'Tulis email, atau hapus centang Email.';
        }
        if (!empty($in['kontak_wa']) && trim((string) ($in['wa'] ?? '')) === '') {
            $errors['wa'] = 'Tulis nomor WhatsApp, atau hapus centang WhatsApp.';
        }
        if (($in['identitas'] ?? '') === 'akun' && !$accountAllowed) {
            $errors['identitas'] = 'Masuk sebagai penyintas dulu untuk mengirim dengan akun, atau pilih Anonim.';
        }
        return $errors;
    }

    public static function initialUrgency(bool $inDanger, string $ongoing): string
    {
        return $inDanger ? 'tinggi' : ($ongoing === 'ya' ? 'sedang' : 'rendah');
    }

    /**
     * Creates the report, its first status log, and attachments in one transaction.
     *
     * Peran pelapor, jawaban usia, dan status kasus anak ikut tersimpan. Nilai
     * `is_child` dan urgensi datang dari `ReportIntake::derive` supaya form,
     * seed, dan antrean memakai aturan yang sama (Req 2.9, 2.10, 4.3, 4.7).
     * Kontak korban dari saksi disimpan terenkripsi, tidak pernah plaintext
     * (Req 5.9). Laporan anonim tetap tanpa user id, email, atau IP di kolom
     * baru (Req 10.4).
     *
     * @param array $in validated input  @param array $files list of ['tmp_name', 'size', 'mime']
     * @return array{id:int, code:string, pin:string, reporter_type:string, is_child:bool}
     */
    public static function create(array $in, ?int $userId, array $files): array
    {
        $code = ReportCode::generate(static fn (string $c) => self::codeExists($c));
        $pin = ReportCode::pin();
        $channels = array_keys(array_filter(['pesan' => true, 'email' => !empty($in['kontak_email']), 'wa' => !empty($in['kontak_wa'])]));
        $contact = array_filter(['email' => !empty($in['kontak_email']) ? trim($in['email']) : null, 'wa' => !empty($in['kontak_wa']) ? trim($in['wa']) : null]);
        $inDanger = $in['bahaya'] === 'ya';

        $intake = ReportIntake::derive($in);
        $reporterType = self::choice($in['peran'] ?? null, ReportIntake::REPORTER, 'korban');
        $ageAnswer = self::choice($in['usia'] ?? null, ReportIntake::AGE, '');
        // Hanya saksi yang menitipkan kontak korban (Req 5.9).
        $victimContact = $reporterType === 'saksi'
            ? mb_substr(trim((string) ($in['kontak_korban'] ?? '')), 0, self::VICTIM_CONTACT_MAX)
            : '';

        $pdo = Database::pdo();
        $stored = [];
        $pdo->beginTransaction();
        try {
            $id = Database::insert(
                'INSERT INTO reports (code, pin_hash, user_id, category, in_danger, ongoing, urgency, status, province, city, place_type, perpetrator_relation,
                    incident_date, chronology_enc, contact_channels, contact_enc, reporter_type, victim_age_answer, is_child, victim_contact_enc, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, \'diterima\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $code, password_hash($pin, PASSWORD_DEFAULT), $userId, $in['jenis'], (int) $inDanger, $in['berlangsung'],
                    $intake['urgency'],
                    $in['provinsi'] ?: null, $in['kota'] ?: null, $in['tempat'] ?: null, $in['pelaku'] ?: null, $in['tanggal'] ?: null,
                    Crypto::enc(trim($in['kronologi'])), implode(',', $channels), $contact ? Crypto::enc(json_encode($contact)) : null,
                    $reporterType, $ageAnswer !== '' ? $ageAnswer : null, (int) $intake['is_child'],
                    $victimContact !== '' ? Crypto::enc($victimContact) : null,
                    now(), now(),
                ]
            );
            Database::run('INSERT INTO report_status_logs (report_id, from_status, to_status, actor_id, created_at) VALUES (?, NULL, \'diterima\', NULL, ?)', [$id, now()]);
            foreach ($files as $f) {
                $name = Upload::store($f['tmp_name']);
                $stored[] = $name;
                Database::run(
                    'INSERT INTO report_attachments (report_id, stored_name, original_name_enc, mime, size_bytes, created_at) VALUES (?, ?, ?, ?, ?, ?)',
                    [$id, $name, Crypto::enc($f['name'] !== '' ? $f['name'] : 'bukti'), $f['mime'], (int) $f['size'], now()]
                );
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            foreach ($stored as $name) {
                Upload::delete($name);
            }
            throw $e;
        }
        return ['id' => $id, 'code' => $code, 'pin' => $pin, 'reporter_type' => $reporterType, 'is_child' => $intake['is_child']];
    }

    /**
     * Nilai `$raw` bila termasuk `$allowed`, selain itu `$fallback`. Masukan
     * yang sudah lolos validasi selalu cocok; ini pagar untuk pemanggil lain
     * seperti `bin/seed-demo.php` yang tidak mengirim pilihan baru.
     *
     * @param list<string> $allowed
     */
    private static function choice(mixed $raw, array $allowed, string $fallback): string
    {
        $value = is_string($raw) ? trim($raw) : '';
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    public static function codeExists(string $code): bool
    {
        return (bool) Database::value('SELECT COUNT(*) FROM reports WHERE code = ?', [$code]);
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM reports WHERE id = ?', [$id]);
    }

    public static function findByCode(string $code): ?array
    {
        return Database::one('SELECT * FROM reports WHERE code = ?', [$code]);
    }

    /**
     * Req 10.1 (pertahanan berlapis): buang kolom terenkripsi dari baris
     * laporan sebelum diteruskan ke lapisan tampilan yang tidak berhak
     * membacanya, misalnya halaman admin. Admin tidak punya jalur dekripsi,
     * tetapi ciphertext pun tidak perlu sampai ke view.
     */
    public static function withoutCiphertext(array $r): array
    {
        unset($r['chronology_enc'], $r['contact_enc'], $r['victim_contact_enc']);
        return $r;
    }

    public static function forUser(int $userId): array
    {
        return Database::all(
            'SELECT r.*, u.name AS companion_name FROM reports r LEFT JOIN users u ON u.id = r.assigned_to WHERE r.user_id = ? ORDER BY r.updated_at DESC',
            [$userId]
        );
    }

    public static function chronology(array $r): string
    {
        return Crypto::dec($r['chronology_enc']) ?? '[Tidak dapat didekripsi. Periksa APP_KEY.]';
    }

    /** @return array{email?:string, wa?:string} */
    public static function contact(array $r): array
    {
        $json = Crypto::dec($r['contact_enc']);
        return $json ? (json_decode($json, true) ?: []) : [];
    }

    public static function channelLabels(array $r): string
    {
        $map = ['pesan' => 'Pesan Haven', 'email' => 'email', 'wa' => 'WhatsApp'];
        $out = array_map(static fn ($c) => $map[$c] ?? $c, array_filter(explode(',', (string) $r['contact_channels'])));
        return $out ? ucfirst(implode(', ', $out)) : 'Pesan Haven';
    }

    public static function area(array $r): string
    {
        return implode(', ', array_filter([$r['city'], $r['province']])) ?: 'Tidak disebutkan';
    }

    /** Status log, oldest first, with decrypted notes and actor names. */
    public static function statusLogs(int $reportId): array
    {
        $rows = Database::all(
            'SELECT l.*, u.name AS actor_name, u.role AS actor_role FROM report_status_logs l LEFT JOIN users u ON u.id = l.actor_id WHERE l.report_id = ? ORDER BY l.id',
            [$reportId]
        );
        foreach ($rows as &$row) {
            $row['note'] = Crypto::dec($row['note_enc']);
        }
        return $rows;
    }

    public static function attachments(int $reportId): array
    {
        return Database::all('SELECT id, mime, size_bytes, created_at FROM report_attachments WHERE report_id = ? ORDER BY id', [$reportId]);
    }

    public static function attachmentCount(int $reportId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM report_attachments WHERE report_id = ?', [$reportId]);
    }

    /** Link an anonymous report to the signed-in survivor after a correct PIN. */
    public static function linkToUser(int $reportId, int $userId): void
    {
        Database::run('UPDATE reports SET user_id = ?, updated_at = ? WHERE id = ? AND user_id IS NULL', [$userId, now(), $reportId]);
        // Earlier anonymous notifications move to the account inbox.
        Database::run('UPDATE notifications SET user_id = ? WHERE report_id = ? AND user_id IS NULL', [$userId, $reportId]);
    }

    /* ---------- Admin queue ---------- */

    /** @return array{0:string, 1:array} WHERE clause and params from queue filters */
    private static function queueWhere(array $f): array
    {
        $w = ['1=1'];
        $p = [];
        if (!empty($f['status']) && isset(self::STATUS[$f['status']])) {
            $w[] = 'r.status = ?';
            $p[] = $f['status'];
        }
        if (!empty($f['urgensi']) && isset(self::URGENCY[$f['urgensi']])) {
            $w[] = 'r.urgency = ?';
            $p[] = $f['urgensi'];
        }
        if (!empty($f['jenis']) && isset(self::CATEGORIES[$f['jenis']])) {
            $w[] = 'r.category = ?';
            $p[] = $f['jenis'];
        }
        // Req 4.8: filter kasus anak. '1' hanya kasus anak, '0' hanya yang bukan.
        if (isset(self::CHILD_FILTER[(string) ($f['anak'] ?? '')])) {
            $w[] = 'r.is_child = ?';
            $p[] = (int) $f['anak'];
        }
        if (!empty($f['pendamping'])) {
            $w[] = 'r.assigned_to = ?';
            $p[] = (int) $f['pendamping'];
        }
        if (!empty($f['q'])) {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $w[] = '(r.code LIKE ? OR r.city LIKE ? OR r.province LIKE ?)';
            array_push($p, $like, $like, $like);
        }
        return [implode(' AND ', $w), $p];
    }

    public static function queueCount(array $filters): int
    {
        [$where, $params] = self::queueWhere($filters);
        return (int) Database::value("SELECT COUNT(*) FROM reports r WHERE $where", $params);
    }

    /**
     * New and urgent first: diterima, then "in danger", then urgency, newest first.
     *
     * `last_survivor_at` dan `last_reply_at` adalah kolom turunan yang dibaca
     * `Sla::isOverdue` untuk lencana "Lewat SLA" (Req 6.8). `last_reply_at`
     * hanya dari pesan `companion`: pesan `system` bukan balasan pendamping,
     * sama seperti yang dipakai `bin/reminders.php`.
     */
    public static function queue(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = self::queueWhere($filters);
        return Database::all(
            "SELECT r.id, r.code, r.user_id, r.category, r.in_danger, r.urgency, r.status, r.province, r.city, r.reporter_type, r.is_child, r.created_at, r.updated_at, u.name AS companion_name,
               (SELECT MAX(m.created_at) FROM messages m WHERE m.report_id = r.id AND m.sender_role = 'survivor')  AS last_survivor_at,
               (SELECT MAX(m.created_at) FROM messages m WHERE m.report_id = r.id AND m.sender_role = 'companion') AS last_reply_at
             FROM reports r LEFT JOIN users u ON u.id = r.assigned_to
             WHERE $where
             ORDER BY (r.status = 'diterima') DESC, r.in_danger DESC, FIELD(r.urgency, 'tinggi', 'sedang', 'rendah'), r.created_at DESC
             LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    public static function setUrgency(int $id, string $urgency): void
    {
        Database::run('UPDATE reports SET urgency = ?, updated_at = ? WHERE id = ?', [$urgency, now(), $id]);
    }

    public static function reassign(int $id, int $companionId): void
    {
        Database::run('UPDATE reports SET assigned_to = ?, updated_at = ? WHERE id = ?', [$companionId, now(), $id]);
    }

    /* ---------- Companion ---------- */

    public static function assignedTo(int $companionId, array $f): array
    {
        $w = ['r.assigned_to = ?'];
        $p = [$companionId];
        if (!empty($f['status']) && isset(self::STATUS[$f['status']])) {
            $w[] = 'r.status = ?';
            $p[] = $f['status'];
        }
        if (!empty($f['urgensi']) && isset(self::URGENCY[$f['urgensi']])) {
            $w[] = 'r.urgency = ?';
            $p[] = $f['urgensi'];
        }
        if (!empty($f['q'])) {
            $w[] = 'r.code LIKE ?';
            $p[] = '%' . addcslashes(strtoupper($f['q']), '%_\\') . '%';
        }
        return Database::all(
            "SELECT r.*,
               (SELECT COUNT(*) FROM messages m WHERE m.report_id = r.id AND m.sender_role = 'survivor' AND m.read_at IS NULL) AS unread,
               GREATEST(r.updated_at, COALESCE((SELECT MAX(m.created_at) FROM messages m WHERE m.report_id = r.id), r.updated_at)) AS last_activity
             FROM reports r WHERE " . implode(' AND ', $w) . "
             ORDER BY (r.status IN ('selesai','ditutup')), unread DESC, FIELD(r.urgency, 'tinggi', 'sedang', 'rendah'), last_activity DESC",
            $p
        );
    }

    public static function activeCountFor(int $companionId): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM reports WHERE assigned_to = ? AND status IN ('didampingi','dirujuk')", [$companionId]);
    }

    /**
     * Active companions in a region first, then by lightest workload.
     *
     * `child_trained` ikut terpilih karena `Assignment::candidates` memakainya
     * untuk menyaring kandidat kasus anak (Req 4.9). Tanpa kolom ini setiap
     * kasus anak akan terbaca sebagai wilayah tanpa pendamping terlatih.
     */
    public static function companionsByLoad(?string $city): array
    {
        return Database::all(
            "SELECT u.id, u.name, u.region, u.child_trained,
               (SELECT COUNT(*) FROM reports r WHERE r.assigned_to = u.id AND r.status IN ('didampingi','dirujuk')) AS active
             FROM users u WHERE u.role = 'companion' AND u.is_active = 1
             ORDER BY (u.region <=> ?) DESC, active, u.name",
            [$city]
        );
    }
}
