<?php
declare(strict_types=1);

/** Module 2: submitting a report (anonymous or with a survivor account). */
final class ReportController
{
    private static function accountAllowed(): bool
    {
        return Auth::role() === 'survivor';
    }

    public function create(): void
    {
        View::show('pages/report/form', [
            'title' => 'Buat laporan | Haven',
            'active' => 'lapor',
            // Form lapor memakai tombol keluar cepat berlabel (Req 8.17).
            'quickExit' => 'labeled',
            'accountAllowed' => self::accountAllowed(),
            'defaultIdentity' => self::accountAllowed() ? 'akun' : 'anonim',
        ]);
    }

    public function store(): void
    {
        $fields = ['jenis', 'usia', 'peran', 'bahaya', 'kronologi', 'berlangsung', 'tanggal', 'tempat', 'provinsi', 'kota', 'pelaku', 'identitas', 'email', 'wa', 'kontak_email', 'kontak_wa', 'kontak_korban', 'setuju'];
        $in = [];
        foreach ($fields as $f) {
            $in[$f] = input($f);
        }

        // Ambang dan jendela rate limit sama untuk korban maupun saksi (Req 5.10).
        $limiter = RateLimiter::default();
        $key = 'report:ip:' . client_ip();
        if ($limiter->tooManyAttempts($key, 5)) {
            throw new HttpException(429, 'Terlalu banyak laporan dari jaringan ini dalam satu jam. Coba lagi dalam ' . RateLimiter::waitText($limiter->availableIn($key)) . ', atau hubungi SAPA 129.');
        }

        // Pilihan wajib di awal form lebih dulu, supaya pesannya yang terbaca
        // bila kategori juga diperiksa Report::validationErrors (Req 2.6, 4.2, 5.1).
        $errors = ReportIntake::intakeErrors($in) + Report::validationErrors($in, self::accountAllowed());

        $files = [];
        $uploads = Upload::normalize($_FILES['bukti'] ?? null);
        if (count($uploads) > Upload::MAX_FILES) {
            $errors['bukti'] = 'Maksimal ' . Upload::MAX_FILES . ' file bukti.';
        } else {
            foreach ($uploads as $i => $u) {
                $err = Upload::check((string) $u['tmp_name'], (int) $u['size'], (int) $u['error'], 'File ke-' . ($i + 1));
                if ($err !== null) {
                    $errors['bukti'] = $err;
                    break;
                }
                $files[] = ['tmp_name' => $u['tmp_name'], 'size' => (int) $u['size'], 'name' => mb_substr((string) $u['name'], 0, 200), 'mime' => Upload::mime($u['tmp_name'])];
            }
        }

        if ($errors) {
            back_with('/lapor', $errors, $in);
        }

        $limiter->hit($key, 3600);
        $userId = ($in['identitas'] === 'akun' && self::accountAllowed()) ? Auth::id() : null;
        $created = Report::create($in, $userId, $files);

        $actor = $userId ? Auth::user() : null;
        $actorLabel = $userId ? null : 'Anonim';
        $byWitness = $created['reporter_type'] === 'saksi';
        Audit::log(
            'report.create',
            'report',
            $created['code'],
            Report::CATEGORIES[$in['jenis']] . ($userId ? ' · berakun' : ' · anonim') . ($byWitness ? ' · saksi' : '') . ($files ? ' · ' . count($files) . ' bukti' : ''),
            $actor,
            $actorLabel
        );
        if ($created['is_child']) {
            // Req 4.11: kode laporan, jenis peristiwa, dan waktu saja. Kronologi
            // tidak pernah masuk audit (Req 10.2); waktunya dari audit_logs.created_at.
            Audit::log('report.child_case', 'report', $created['code'], 'Child_Case created · urgensi tinggi', $actor, $actorLabel);
        }
        Notifier::admins($created['id'], 'laporan', 'Laporan baru: ' . $created['code'] . ($in['bahaya'] === 'ya' ? ' (menyatakan dalam bahaya)' : ''), '/admin/kasus/' . $created['code']);

        CaseAccess::grant($created['id']);
        // `child` dipakai halaman terkirim untuk menampilkan panel rujukan anak (Req 4.4),
        // `danger` untuk batas waktu balasan yang berlaku bagi laporan ini (Req 6.2).
        Session::flash('new_report', ['code' => $created['code'], 'pin' => $created['pin'], 'reporter' => $created['reporter_type'], 'child' => $created['is_child'], 'danger' => $in['bahaya'] === 'ya']);
        redirect('/lapor/terkirim');
    }

    /** Shows code + PIN exactly once (flash data). */
    public function sent(): void
    {
        $r = Session::getFlash('new_report');
        if (!is_array($r)) {
            redirect('/lacak');
        }
        View::show('pages/report/sent', [
            'title' => 'Laporan terkirim | Haven',
            'active' => 'lapor',
            'code' => $r['code'],
            'pin' => $r['pin'],
            // Req 5.5: saksi perlu tahu Haven tidak menghubungi korban tanpa persetujuan.
            'byWitness' => ($r['reporter'] ?? 'korban') === 'saksi',
            // Layanan 24 jam untuk dihubungi sekarang. Tampilan memakai
            // ServiceRules::publicView, jadi nomor yang belum dicek tidak tampil (Req 1.1).
            'services' => Service::search(['h24' => '1'], 12),
            // Req 4.4, 4.5: kasus anak melihat panel tiga lembaga perlindungan anak.
            'childPanel' => !empty($r['child']) ? ChildReferral::panel(Service::findChildServices()) : null,
            // Req 6.2: batas waktu balasan laporan ini, dalam jam, dari konfigurasi SLA.
            'slaHours' => sla_hours(!empty($r['danger'])),
        ]);
    }
}
