<?php
declare(strict_types=1);

/*
 * Menulis tabel tim di README.md dari app/config/team.php (Req 9.7, 9.9).
 *   php bin/sync-team.php           tulis ulang tabel di antara penanda team:start/team:end
 *   php bin/sync-team.php --check    hanya memeriksa, keluar 1 bila ada masalah
 *
 * Mode --check keluar dengan kode bukan nol bila ada anggota tanpa NIM (dan
 * menyebut namanya), data tim tidak sah, atau tabel di README.md sudah tidak
 * sama dengan app/config/team.php (Req 9.8).
 */

require dirname(__DIR__) . '/app/bootstrap.php';

const TEAM_START = '<!-- team:start -->';
const TEAM_END = '<!-- team:end -->';
const NIM_PLACEHOLDER = '_(belum diisi)_';
const MAX_NAME = 100;
const MAX_NIM = 20;
const MAX_MODULES = 10;

$check = in_array('--check', $argv, true);
$teamFile = APP_PATH . '/config/team.php';
$readmeFile = ROOT_PATH . '/README.md';

$team = team_load($teamFile);

$errors = team_errors($team);
$missingNim = team_missing_nim($team);
$table = team_table($team);

if ($errors !== []) {
    // Data tidak sah: tidak ditulis ke README dalam mode apa pun.
    foreach ($errors as $message) {
        fwrite(STDERR, "- $message\n");
    }
    fwrite(STDERR, "Perbaiki app/config/team.php lebih dulu.\n");
    exit(1);
}

if ($check) {
    $issues = $missingNim === [] ? [] : ['NIM belum diisi untuk: ' . implode(', ', $missingNim) . '.'];

    $current = readme_block($readmeFile);
    if (trim($current) !== trim($table)) {
        $issues[] = 'Tabel tim di README.md tidak sama dengan app/config/team.php. Jalankan: php bin/sync-team.php';
    }

    if ($issues !== []) {
        fwrite(STDERR, "Data tim belum siap:\n");
        foreach ($issues as $message) {
            fwrite(STDERR, "- $message\n");
        }
        exit(1);
    }

    echo "Data tim lengkap dan tabel di README.md sudah sama.\n";
    exit(0);
}

readme_write($readmeFile, $table);
echo 'README.md diperbarui: ' . count($team) . " anggota tim.\n";

foreach ($missingNim as $name) {
    fwrite(STDERR, "Peringatan: NIM $name masih kosong, tabel menulis " . NIM_PLACEHOLDER . ".\n");
}

exit(0);

/* ---------- Data tim ---------- */

/** @return list<array<string,mixed>> */
function team_load(string $file): array
{
    if (!is_file($file)) {
        fwrite(STDERR, "Berkas data tim tidak ada: $file\n");
        exit(1);
    }

    $data = require $file;
    if (!is_array($data) || $data === []) {
        fwrite(STDERR, "$file harus mengembalikan daftar anggota yang tidak kosong.\n");
        exit(1);
    }

    return array_values(array_map(static fn ($member) => is_array($member) ? $member : [], $data));
}

/**
 * Pelanggaran bentuk data (Req 9.1): nama 1 sampai 100 karakter, NIM maksimal
 * 20 karakter, dan 1 sampai 10 modul per anggota. NIM kosong bukan bagian dari
 * daftar ini, karena ditangani terpisah oleh team_missing_nim().
 *
 * @param list<array<string,mixed>> $team
 * @return list<string>
 */
function team_errors(array $team): array
{
    $errors = [];
    $seen = [];

    foreach ($team as $i => $member) {
        $label = 'Anggota #' . ($i + 1);
        $name = is_string($member['name'] ?? null) ? trim($member['name']) : '';

        if ($name === '') {
            $errors[] = "$label tidak punya nama.";
        } elseif (mb_strlen($name) > MAX_NAME) {
            $errors[] = "$label: nama lebih dari " . MAX_NAME . ' karakter.';
        } else {
            $label = $name;
            if (isset($seen[$name])) {
                $errors[] = "$name muncul dua kali.";
            }
            $seen[$name] = true;
        }

        $nim = is_string($member['nim'] ?? null) ? trim($member['nim']) : null;
        if ($nim === null) {
            $errors[] = "$label: NIM harus berupa teks.";
        } elseif (mb_strlen($nim) > MAX_NIM) {
            $errors[] = "$label: NIM lebih dari " . MAX_NIM . ' karakter.';
        }

        $modules = $member['modules'] ?? null;
        if (!is_array($modules) || $modules === []) {
            $errors[] = "$label: harus punya minimal satu modul.";
            continue;
        }
        if (count($modules) > MAX_MODULES) {
            $errors[] = "$label: lebih dari " . MAX_MODULES . ' modul.';
        }
        foreach ($modules as $module) {
            if (!is_string($module) || trim($module) === '') {
                $errors[] = "$label: ada nama modul yang kosong.";
                break;
            }
        }
    }

    return $errors;
}

/**
 * Nama anggota yang NIM-nya masih kosong (Req 9.8).
 *
 * @param list<array<string,mixed>> $team
 * @return list<string>
 */
function team_missing_nim(array $team): array
{
    $missing = [];
    foreach ($team as $i => $member) {
        $nim = is_string($member['nim'] ?? null) ? trim($member['nim']) : '';
        if ($nim === '') {
            $name = is_string($member['name'] ?? null) ? trim($member['name']) : '';
            $missing[] = $name !== '' ? $name : 'anggota #' . ($i + 1);
        }
    }

    return $missing;
}

/* ---------- Tabel Markdown ---------- */

/** @param list<array<string,mixed>> $team */
function team_table(array $team): string
{
    $rows = ['| Anggota | NIM | Modul |', '|---|---|---|'];

    foreach ($team as $member) {
        $nim = md_cell((string) ($member['nim'] ?? ''));
        $rows[] = sprintf(
            '| %s | %s | %s |',
            md_cell((string) ($member['name'] ?? '')),
            $nim === '' ? NIM_PLACEHOLDER : $nim,
            md_cell(implode(', ', array_map('strval', (array) ($member['modules'] ?? []))))
        );
    }

    return implode("\n", $rows);
}

/** Pipa akan memecah kolom tabel Markdown, jadi perlu di-escape. */
function md_cell(string $value): string
{
    return str_replace('|', '\|', trim($value));
}

/* ---------- README ---------- */

/** Isi di antara penanda, atau keluar bila penandanya tidak ada. */
function readme_block(string $file): string
{
    [, $block] = readme_parts($file);

    return $block;
}

function readme_write(string $file, string $table): void
{
    [$readme, , $from, $to] = readme_parts($file);

    $updated = substr($readme, 0, $from) . "\n\n" . $table . "\n\n" . substr($readme, $to);
    if (file_put_contents($file, $updated) === false) {
        fwrite(STDERR, "Gagal menulis $file\n");
        exit(1);
    }
}

/** @return array{0:string, 1:string, 2:int, 3:int} isi README, isi blok, posisi awal blok, posisi akhir blok */
function readme_parts(string $file): array
{
    $readme = is_file($file) ? (string) file_get_contents($file) : '';
    if ($readme === '') {
        fwrite(STDERR, "Tidak bisa membaca $file\n");
        exit(1);
    }

    $start = strpos($readme, TEAM_START);
    $end = strpos($readme, TEAM_END);
    if ($start === false || $end === false || $end < $start) {
        fwrite(STDERR, 'Penanda ' . TEAM_START . ' dan ' . TEAM_END . " harus ada di $file, dengan urutan itu.\n");
        exit(1);
    }

    $from = $start + strlen(TEAM_START);

    return [$readme, substr($readme, $from, $end - $from), $from, $end];
}
