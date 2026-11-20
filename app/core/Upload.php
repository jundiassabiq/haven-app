<?php
declare(strict_types=1);

/**
 * Evidence uploads. Files are type-checked by content (finfo), renamed to random names,
 * stored in storage/uploads (outside the web root), and only served through a permission check.
 */
final class Upload
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_FILES = 5;
    public const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];

    /** Turns $_FILES['x'] (multiple) into a list of single files, dropping empty slots. */
    public static function normalize(?array $files): array
    {
        if (!$files || !isset($files['name'])) {
            return [];
        }
        $out = [];
        if (is_array($files['name'])) {
            foreach (array_keys($files['name']) as $i) {
                $f = ['name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
                if ((int) $f['error'] !== UPLOAD_ERR_NO_FILE) {
                    $out[] = $f;
                }
            }
        } elseif ((int) $files['error'] !== UPLOAD_ERR_NO_FILE) {
            $out[] = $files;
        }
        return $out;
    }

    /** @return string|null error message, or null when the file is acceptable */
    public static function check(string $path, int $size, int $error, string $name = 'File'): ?string
    {
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE || $size > self::MAX_BYTES) {
            return "$name lebih dari 5 MB.";
        }
        if ($error !== UPLOAD_ERR_OK || !is_file($path)) {
            return "$name gagal diunggah. Coba pilih lagi.";
        }
        if ($size === 0) {
            return "$name kosong.";
        }
        $mime = self::mime($path);
        if (!isset(self::TYPES[$mime])) {
            return "$name bukan JPG, PNG, atau PDF.";
        }
        return null;
    }

    public static function mime(string $path): string
    {
        return (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
    }

    /** Moves an uploaded file into storage. Returns the random stored name. */
    public static function store(string $tmpPath): string
    {
        $dir = self::dir();
        $name = bin2hex(random_bytes(32));
        $ok = PHP_SAPI === 'cli' ? rename($tmpPath, "$dir/$name") : move_uploaded_file($tmpPath, "$dir/$name");
        if (!$ok) {
            throw new RuntimeException('Gagal menyimpan file.');
        }
        return $name;
    }

    public static function path(string $storedName): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $storedName)) {
            throw new InvalidArgumentException('Nama file tidak valid.');
        }
        return self::dir() . '/' . $storedName;
    }

    public static function delete(string $storedName): void
    {
        $p = self::path($storedName);
        if (is_file($p)) {
            @unlink($p);
        }
    }

    private static function dir(): string
    {
        $dir = STORAGE_PATH . '/uploads';
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        return $dir;
    }
}
