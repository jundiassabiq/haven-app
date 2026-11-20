<?php
declare(strict_types=1);

/** Module 3: open a report with code + PIN, close that access, or link it to an account. */
final class TrackController
{
    private const MAX = 5;
    private const WINDOW = 15 * 60;

    public function form(): void
    {
        View::show('pages/track/form', [
            'title' => 'Lacak laporan | Haven',
            'active' => 'lacak',
            // Halaman lacak memakai tombol keluar cepat berlabel (Req 8.17).
            'quickExit' => 'labeled',
            'formError' => Session::getFlash('form_error'),
        ]);
    }

    /**
     * Shared code+PIN check with rate limiting per IP and per code.
     * @return array report on success; otherwise flashes an error and redirects to $back
     */
    private function verify(string $back): array
    {
        $code = ReportCode::normalize(input('kode'));
        $pin = preg_replace('/\D/', '', input('pin'));
        $errors = Validator::make(['kode' => $code, 'pin' => $pin], [
            'kode' => ['required', 'regex:' . ReportCode::PATTERN],
            'pin' => ['required', 'regex:/^[0-9]{6}$/'],
        ], [
            'kode.required' => 'Masukkan kode laporanmu.',
            'kode.regex' => 'Kode diawali HV- lalu 6 huruf atau angka, contoh HV-9B2X6P.',
            'pin.required' => 'Masukkan PIN 6 digit.',
            'pin.regex' => 'PIN terdiri dari 6 angka.',
        ]);
        if ($errors) {
            back_with($back, $errors, ['kode' => $code]);
        }

        $limiter = RateLimiter::default();
        $keys = ['lacak:ip:' . client_ip(), 'lacak:code:' . $code];
        foreach ($keys as $k) {
            if ($limiter->tooManyAttempts($k, self::MAX)) {
                Session::flash('form_error', 'Pelacakan dikunci sementara karena terlalu banyak percobaan. Coba lagi dalam ' . RateLimiter::waitText($limiter->availableIn($k)) . '.');
                back_with($back, [], ['kode' => $code]);
            }
        }

        $report = Report::findByCode($code);
        // Verify against a real hash even for unknown codes, so timing does not reveal which codes exist.
        $ok = password_verify($pin, $report['pin_hash'] ?? '$2y$10$0BUzX6IVDmwuOmiHaXo71uNPudbhIETCLKAwIxet5zNdm4vK31kxW');
        if (!$report || !$ok) {
            foreach ($keys as $k) {
                $limiter->hit($k, self::WINDOW);
            }
            $n = $limiter->attempts($keys[0]);
            Audit::log('access.denied', 'report', $report ? $code : null, 'Kode/PIN salah (' . min($n, self::MAX) . '/' . self::MAX . ')', null, Auth::user()['name'] ?? 'Anonim');
            $left = self::MAX - $n;
            Session::flash('form_error', 'Kode laporan atau PIN tidak cocok.' . ($left > 0 ? " Sisa $left percobaan." : ' Pelacakan dikunci 15 menit.'));
            back_with($back, [], ['kode' => $code]);
        }
        foreach ($keys as $k) {
            $limiter->clear($k);
        }
        return $report;
    }

    public function track(): void
    {
        $report = $this->verify('/lacak');
        CaseAccess::grant((int) $report['id']);
        Audit::log('report.track', 'report', $report['code'], 'Dibuka dengan kode + PIN', null, Auth::user()['name'] ?? 'Anonim');
        redirect('/kasus/' . $report['code']);
    }

    public function close(array $p): void
    {
        CaseAccess::revoke();
        Session::flash('success', 'Sesi laporan ditutup di perangkat ini.');
        redirect('/lacak');
    }

    public function link(): void
    {
        $report = $this->verify('/penyintas/laporan');
        $me = Auth::user();
        if ($report['user_id'] !== null && (int) $report['user_id'] !== (int) $me['id']) {
            Session::flash('error', 'Laporan ini sudah tertaut ke akun lain.');
            redirect('/penyintas/laporan');
        }
        if ($report['user_id'] === null) {
            Report::linkToUser((int) $report['id'], (int) $me['id']);
            Audit::log('report.link', 'report', $report['code'], 'Ditautkan ke akun');
        }
        Session::flash('success', 'Laporan ' . $report['code'] . ' sekarang ada di akunmu.');
        redirect('/penyintas/laporan');
    }
}
