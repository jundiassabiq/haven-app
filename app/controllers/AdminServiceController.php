<?php
declare(strict_types=1);

/** Module 9 (admin): create, edit, verify, and delete directory entries. */
final class AdminServiceController
{
    public function index(): void
    {
        $services = Service::all();
        View::show('pages/admin/services', [
            'title' => 'Layanan | Admin Haven',
            'active' => 'layanan',
            'services' => $services,
            // Aturan verifikasi murni: hari ini disuntikkan dari sini (Req 1.7, 1.9).
            'today' => new DateTimeImmutable('now'),
            'unverifiedCounts' => ServiceRules::unverifiedCounts($services),
        ], 'app');
    }

    public function create(): void
    {
        View::show('pages/admin/service-form', ['title' => 'Tambah layanan | Admin Haven', 'active' => 'layanan', 'service' => null, 'referrals' => 0], 'app');
    }

    private static function loadOr404(int $id): array
    {
        $s = Service::find($id);
        if (!$s) {
            throw new HttpException(404);
        }
        return $s;
    }

    public function edit(array $p): void
    {
        $s = self::loadOr404((int) $p['id']);
        View::show('pages/admin/service-form', [
            'title' => $s['name'] . ' | Admin Haven',
            'active' => 'layanan',
            'service' => $s,
            'referrals' => (int) Database::value('SELECT COUNT(*) FROM reports WHERE service_id = ?', [$s['id']]),
        ], 'app');
    }

    private static function input(): array
    {
        $keys = ['name', 'type', 'province', 'city', 'address', 'phone', 'whatsapp', 'hours', 'tags', 'verified_at', 'verified_note', '_sent'];
        $in = [];
        foreach ($keys as $k) {
            $in[$k] = input($k);
        }
        $in['is_verified'] = input_bool('is_verified') ? '1' : '';
        $in['is_24h'] = input_bool('is_24h') ? '1' : '';
        if ($in['province'] === 'Nasional') {
            $in['city'] = 'Nasional';
        }
        return $in;
    }

    public function store(): void
    {
        $in = self::input();
        $errors = Service::validationErrors($in, new DateTimeImmutable('now'));
        if ($errors) {
            // Tidak ada baris yang ditulis; nilai lama kembali ke form (Req 1.4, 1.5).
            back_with('/admin/layanan/baru', $errors, $in);
        }
        $verified = ServiceRules::nextVerified([], $in);
        $id = Service::save($in, null);
        Audit::log(
            'service.save',
            'service',
            (string) $id,
            'verifikasi: ' . Service::verifyLabel(false) . '→' . Service::verifyLabel($verified) . ' · menambah ' . $in['name']
        );
        Session::flash('success', 'Layanan ' . $in['name'] . ' ditambahkan.');
        redirect('/admin/layanan');
    }

    public function update(array $p): void
    {
        $s = self::loadOr404((int) $p['id']);
        $in = self::input();
        $errors = Service::validationErrors($in, new DateTimeImmutable('now'));
        if ($errors) {
            // Simpanan ditolak: baris lama tetap utuh (Req 1.4, 1.5).
            back_with('/admin/layanan/' . $s['id'], $errors, $in);
        }
        $was = (bool) (int) $s['is_verified'];
        $verified = ServiceRules::nextVerified($s, $in);
        Service::save($in, (int) $s['id'], $s);
        Audit::log(
            'service.save',
            'service',
            (string) $s['id'],
            'verifikasi: ' . Service::verifyLabel($was) . '→' . Service::verifyLabel($verified) . ' · ' . $in['name']
        );

        $note = match (true) {
            // Kontak layanan terverifikasi berubah, jadi statusnya kembali perlu dicek (Req 1.6).
            $was && !$verified && !empty($in['is_verified']) => ' Nomornya berubah, jadi layanan ini kembali berlabel "'
                . ServiceRules::UNVERIFIED_LABEL . '" sampai kamu menandainya terverifikasi lagi.',
            !$was && $verified => ' Kontaknya sekarang tampil di direktori publik.',
            $was && !$verified => ' Kontaknya tidak lagi tampil di direktori publik.',
            default => '',
        };
        Session::flash('success', 'Layanan disimpan.' . $note);
        redirect('/admin/layanan');
    }

    public function delete(array $p): void
    {
        $s = self::loadOr404((int) $p['id']);
        if (!Service::delete((int) $s['id'])) {
            Session::flash('error', 'Layanan ini dipakai untuk rujukan kasus, jadi tidak bisa dihapus.');
            redirect('/admin/layanan/' . $s['id']);
        }
        Audit::log('service.delete', 'service', (string) $s['id'], 'Menghapus ' . $s['name']);
        Session::flash('success', 'Layanan ' . $s['name'] . ' dihapus.');
        redirect('/admin/layanan');
    }
}
