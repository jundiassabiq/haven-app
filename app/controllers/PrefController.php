<?php
declare(strict_types=1);

/**
 * Pilihan bahasa dan mode tampilan (Req 8.6, 8.13).
 *
 * Keduanya endpoint publik yang mengubah keadaan, jadi:
 * - token CSRF diperiksa `App::run` sebelum metode ini jalan (Req 10.6, 10.9),
 * - `RateLimiter` membatasi jumlah pergantian per alamat IP (Req 10.7, 10.8),
 * - nilai di luar daftar ditolak tanpa mengubah sesi (Req 10.8).
 *
 * Pilihan disimpan di `Session`, bukan di cookie sendiri, supaya ikut hilang saat
 * sesi berakhir dan tidak menambah jejak di perangkat yang dipakai bersama.
 */
final class PrefController
{
    /** Pergantian yang wajar dalam satu jendela; di atas itu permintaan ditolak. */
    private const MAX = 30;

    private const WINDOW = 600;

    /** `POST /bahasa` (Req 8.6). */
    public function setLang(): void
    {
        $this->limit('bahasa');
        $code = I18n::normalizeLang(input('bahasa'));
        if ($code === null) {
            Session::flash('error', 'Bahasa itu belum tersedia. Pilih Bahasa Indonesia atau English.');
            $this->back();
        }
        Session::put(I18n::SESSION_KEY, $code);
        $this->back();
    }

    /** `POST /mode` (Req 8.13). Pilihan pengguna menang atas header `Save-Data`. */
    public function setMode(): void
    {
        $this->limit('mode');
        $choice = Mode::normalizeChoice(input('mode'));
        if ($choice === null) {
            Session::flash('error', 'Mode tampilan itu tidak dikenali. Pilih tampilan lengkap atau ringan.');
            $this->back();
        }
        Session::put(Mode::SESSION_KEY, $choice);
        $this->back();
    }

    /** Menolak permintaan ke-31 dan seterusnya tanpa menyentuh sesi (Req 10.7, 10.8). */
    private function limit(string $name): void
    {
        $limiter = RateLimiter::default();
        $key = 'pref:' . $name . ':ip:' . client_ip();
        if ($limiter->tooManyAttempts($key, self::MAX)) {
            throw new HttpException(429, 'Terlalu banyak pergantian tampilan dari jaringan ini. Coba lagi dalam '
                . RateLimiter::waitText($limiter->availableIn($key)) . '.');
        }
        $limiter->hit($key, self::WINDOW);
    }

    /**
     * Kembali ke halaman yang dibuka pengguna. Hanya jalur internal diterima,
     * supaya kolom tersembunyi ini tidak bisa dipakai mengarahkan orang ke situs
     * lain (open redirect).
     */
    private function back(): never
    {
        $to = input('kembali');
        $safe = $to !== ''
            && str_starts_with($to, '/')
            && !str_starts_with($to, '//')
            && !str_contains($to, '\\')
            && !preg_match('/[\r\n]/', $to);

        redirect($safe ? $to : '/');
    }
}
