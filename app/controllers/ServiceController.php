<?php
declare(strict_types=1);

/** Module 9 (public): directory page and its JSON filter endpoint. */
final class ServiceController
{
    private static function filters(): array
    {
        return [
            'q' => mb_substr(input('q'), 0, 80),
            'provinsi' => mb_substr(input('provinsi'), 0, 60),
            'kota' => mb_substr(input('kota'), 0, 80),
            'jenis' => input('jenis'),
            'h24' => input('h24') === '1' ? '1' : '',
        ];
    }

    public function index(): void
    {
        $f = self::filters();
        View::show('pages/services/index', [
            'title' => 'Layanan bantuan | Haven',
            'active' => 'layanan',
            'services' => Service::search($f),
            'provinces' => Service::provinces(),
            'cities' => Service::cities($f['provinsi']),
            'f' => $f,
        ]);
    }

    public function api(): void
    {
        $f = self::filters();
        $limit = max(1, min(200, (int) ($_GET['limit'] ?? 200)));
        $all = Service::search($f);
        json_response([
            'total' => count($all),
            'services' => array_map([Service::class, 'publicArray'], array_slice($all, 0, $limit)),
            'cities' => Service::cities($f['provinsi']),
        ]);
    }
}
