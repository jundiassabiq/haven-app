<?php
declare(strict_types=1);

/**
 * Klien SMTP kecil tanpa dependensi (Req 6.9).
 *
 * Proyek ini tidak memakai Composer untuk kode aplikasi, jadi pengiriman email
 * dikerjakan sendiri di atas stream socket PHP: STARTTLS atau SSL implisit,
 * AUTH LOGIN, dan satu batas waktu 30 detik untuk seluruh percakapan.
 *
 * Yang ditangani kelas ini hanya I/O. Keputusan boleh-tidaknya mengirim ada di
 * `MailPolicy`, keputusan percobaan ulang ada di `MailQueue`, dan antrean ada
 * di `Mailer`. Setiap kegagalan — koneksi ditolak, balasan tak terduga,
 * kehabisan waktu — dilaporkan sebagai `RuntimeException`, sehingga pemanggil
 * memperlakukan semuanya sebagai satu hal: kirim gagal.
 *
 * Batas waktu dihitung sebagai tenggat tunggal (`$deadline`) untuk koneksi
 * ditambah semua perintah, bukan per operasi. Tanpa itu, server yang membalas
 * tiap perintah dalam 25 detik akan menahan proses jauh lebih lama dari 30
 * detik yang dijanjikan Req 6.9.
 *
 * Pesan galat tidak pernah memuat kata sandi SMTP maupun isi email.
 */
final class SmtpClient
{
    /** Batas waktu seluruh percakapan SMTP dalam detik (Req 6.9). */
    public const TIMEOUT = 30;

    /** Tanpa enkripsi (port 25 lokal, mis. MailHog atau Mailpit). */
    public const ENC_NONE = 'none';

    /** STARTTLS: sambungan biasa lalu dinaikkan ke TLS (umumnya port 587). */
    public const ENC_TLS = 'tls';

    /** TLS implisit sejak byte pertama (umumnya port 465). */
    public const ENC_SSL = 'ssl';

    /** Panjang maksimal satu baris balasan SMTP (RFC 5321: 512 oktet + CRLF). */
    private const LINE_MAX = 515;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $user = '',
        private readonly string $pass = '',
        private readonly string $encryption = self::ENC_NONE,
        private readonly int $timeout = self::TIMEOUT,
    ) {
        if (trim($this->host) === '') {
            throw new RuntimeException('Host SMTP kosong.');
        }
        if ($this->port < 1 || $this->port > 65535) {
            throw new RuntimeException('Port SMTP tidak sah: ' . $this->port . '.');
        }
    }

    /**
     * Klien dari isi `.env` memakai kunci `MailPolicy::ENV_*`.
     *
     * Pemanggil memastikan `MailPolicy::mode()` bernilai `smtp` lebih dulu;
     * host atau port kosong di sini berarti konfigurasi berubah di tengah
     * jalan dan dilaporkan sebagai galat, bukan diam-diam dilewati.
     *
     * @param array<string,mixed> $env
     */
    public static function fromEnv(array $env, int $timeout = self::TIMEOUT): self
    {
        return new self(
            trim((string) ($env[MailPolicy::ENV_HOST] ?? '')),
            (int) trim((string) ($env[MailPolicy::ENV_PORT] ?? '0')),
            trim((string) ($env[MailPolicy::ENV_USER] ?? '')),
            (string) ($env[MailPolicy::ENV_PASS] ?? ''),
            self::normalizeEncryption($env[MailPolicy::ENV_ENCRYPTION] ?? null),
            $timeout,
        );
    }

    /**
     * Nilai `MAIL_ENCRYPTION` yang dipakai: `tls` berarti STARTTLS (port 587),
     * `ssl` berarti TLS implisit (port 465). Nilai lain, termasuk kosong,
     * berarti tanpa enkripsi — dipakai untuk penangkap email lokal.
     */
    public static function normalizeEncryption(mixed $raw): string
    {
        return match (strtolower(trim(is_scalar($raw) ? (string) $raw : ''))) {
            'ssl', 'smtps' => self::ENC_SSL,
            'tls', 'starttls' => self::ENC_TLS,
            default => self::ENC_NONE,
        };
    }

    /**
     * Kirim satu email teks biasa. Mengembalikan void bila server menerima
     * pesan, dan melempar `RuntimeException` untuk setiap kegagalan.
     *
     * Alamat sudah harus tervalidasi oleh pemanggil; di sini CR dan LF dibuang
     * agar alamat tidak bisa menyuntikkan header tambahan.
     */
    public function send(string $from, string $fromName, string $to, string $subject, string $body): void
    {
        $from = self::oneLine($from);
        $to = self::oneLine($to);
        $deadline = microtime(true) + $this->timeout;
        $socket = $this->connect($deadline);

        try {
            $this->expect($socket, $deadline, [220], 'sambutan server');
            $this->greet($socket, $deadline);

            if ($this->encryption === self::ENC_TLS) {
                $this->command($socket, $deadline, 'STARTTLS', [220]);
                if (@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
                    throw new RuntimeException('STARTTLS gagal: sambungan tidak bisa dinaikkan ke TLS.');
                }
                // EHLO diulang setelah TLS: daftar kemampuan sebelum dan sesudah bisa berbeda.
                $this->greet($socket, $deadline);
            }

            $this->authenticate($socket, $deadline);

            $this->command($socket, $deadline, 'MAIL FROM:<' . $from . '>', [250]);
            $this->command($socket, $deadline, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->command($socket, $deadline, 'DATA', [354]);
            $this->write($socket, $deadline, self::message($from, $fromName, $to, $subject, $body) . "\r\n.");
            $this->expect($socket, $deadline, [250], 'akhir isi pesan');

            // Kegagalan QUIT tidak membatalkan pesan yang sudah diterima server.
            try {
                $this->command($socket, $deadline, 'QUIT', [221]);
            } catch (RuntimeException) {
                // Sengaja diabaikan.
            }
        } finally {
            @fclose($socket);
        }
    }

    /**
     * Pesan RFC 5322 lengkap: header, lalu isi dalam base64.
     *
     * Base64 dipilih supaya isi berbahasa Indonesia (UTF-8, baris panjang,
     * baris yang dimulai titik) tidak perlu diproses lagi: alfabetnya tidak
     * memuat titik, jadi tidak ada baris yang bisa menutup blok DATA lebih awal.
     */
    public static function message(string $from, string $fromName, string $to, string $subject, string $body): string
    {
        $name = self::oneLine($fromName);
        $sender = $name === '' ? '<' . $from . '>' : self::encodeHeader($name) . ' <' . $from . '>';

        $headers = [
            'From: ' . $sender,
            'To: <' . $to . '>',
            'Subject: ' . self::encodeHeader(self::oneLine($subject)),
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . self::heloName() . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($body), 76, "\r\n"), "\r\n");
    }

    /** Sambungan TCP atau TLS. Gagal berarti kirim gagal (Req 6.9, 6.11). */
    private function connect(float $deadline)
    {
        $remote = ($this->encryption === self::ENC_SSL ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port;
        $context = stream_context_create(['ssl' => ['peer_name' => $this->host, 'SNI_enabled' => true]]);
        $errno = 0;
        $errstr = '';

        $socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            max(1.0, $deadline - microtime(true)),
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!is_resource($socket)) {
            $reason = trim($errstr) === '' ? 'batas waktu ' . $this->timeout . ' detik terlampaui' : trim($errstr);
            throw new RuntimeException("Koneksi SMTP ke {$this->host}:{$this->port} gagal: $reason ($errno).");
        }

        return $socket;
    }

    /** EHLO, dengan HELO sebagai cadangan untuk server yang hanya bicara SMTP lama. */
    private function greet($socket, float $deadline): void
    {
        try {
            $this->command($socket, $deadline, 'EHLO ' . self::heloName(), [250]);
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), 'batas waktu')) {
                throw $e;
            }
            $this->command($socket, $deadline, 'HELO ' . self::heloName(), [250]);
        }
    }

    /**
     * AUTH LOGIN bila ada pengguna SMTP. Server yang tidak butuh autentikasi
     * dipakai dengan `MAIL_USER` kosong, dan langkah ini dilewati.
     */
    private function authenticate($socket, float $deadline): void
    {
        if ($this->user === '') {
            return;
        }

        $this->command($socket, $deadline, 'AUTH LOGIN', [334]);
        $this->command($socket, $deadline, base64_encode($this->user), [334], 'AUTH LOGIN (nama pengguna)');
        // Label dipakai di pesan galat supaya kata sandi (base64) tidak masuk log.
        $this->command($socket, $deadline, base64_encode($this->pass), [235], 'AUTH LOGIN (kata sandi)');
    }

    /**
     * Satu perintah, lalu balasan yang kodenya harus ada di `$expected`.
     *
     * @param list<int> $expected
     * @param string|null $label teks yang muncul di pesan galat; dipakai agar
     *        kredensial tidak pernah ikut tercatat
     */
    private function command($socket, float $deadline, string $line, array $expected, ?string $label = null): void
    {
        $this->write($socket, $deadline, $line);
        $this->expect($socket, $deadline, $expected, $label ?? $line);
    }

    /** @param list<int> $expected */
    private function expect($socket, float $deadline, array $expected, string $step): void
    {
        [$code, $text] = $this->readReply($socket, $deadline);
        if (!in_array($code, $expected, true)) {
            throw new RuntimeException("SMTP menolak pada tahap \"$step\": $code $text");
        }
    }

    private function write($socket, float $deadline, string $line): void
    {
        $this->applyTimeout($socket, $deadline);
        if (@fwrite($socket, $line . "\r\n") === false) {
            throw new RuntimeException('Gagal menulis ke sambungan SMTP.');
        }
    }

    /**
     * Balasan server, termasuk balasan banyak baris (`250-...` diakhiri
     * `250 ...`).
     *
     * @return array{0:int,1:string} kode balasan dan teksnya
     */
    private function readReply($socket, float $deadline): array
    {
        $lines = [];

        do {
            $this->applyTimeout($socket, $deadline);
            $line = @fgets($socket, self::LINE_MAX);
            if ($line === false) {
                $meta = stream_get_meta_data($socket);
                throw new RuntimeException(
                    !empty($meta['timed_out'])
                        ? 'Server SMTP tidak membalas dalam batas waktu ' . $this->timeout . ' detik.'
                        : 'Sambungan SMTP tertutup sebelum server membalas.'
                );
            }
            $lines[] = rtrim($line, "\r\n");
            $more = strlen($line) > 3 && $line[3] === '-';
        } while ($more);

        $last = $lines[count($lines) - 1];

        return [(int) substr($last, 0, 3), implode(' | ', $lines)];
    }

    /**
     * Sisa waktu dipasang sebagai batas baca/tulis socket. Nol atau kurang
     * berarti tenggat 30 detik sudah lewat: kirim dianggap gagal (Req 6.9).
     */
    private function applyTimeout($socket, float $deadline): void
    {
        $left = $deadline - microtime(true);
        if ($left <= 0) {
            throw new RuntimeException('Percakapan SMTP melewati batas waktu ' . $this->timeout . ' detik.');
        }
        stream_set_timeout($socket, (int) floor($left), (int) (fmod($left, 1.0) * 1_000_000));
    }

    /** Nama yang dipakai di EHLO dan Message-ID. */
    private static function heloName(): string
    {
        $host = gethostname();

        return is_string($host) && preg_match('/^[A-Za-z0-9.\-]+$/', $host) === 1 ? $host : 'localhost';
    }

    /**
     * Header dengan karakter non-ASCII dikodekan sebagai encoded-word UTF-8.
     * Dipotong per 12 karakter supaya setiap bagian tetap di bawah batas 75
     * oktet per encoded-word, lalu dilipat dengan CRLF + spasi.
     */
    private static function encodeHeader(string $text): string
    {
        if (preg_match('/^[\x20-\x7E]*$/', $text) === 1) {
            return $text;
        }

        $parts = array_map(
            static fn (string $chunk): string => '=?UTF-8?B?' . base64_encode($chunk) . '?=',
            mb_str_split($text, 12)
        );

        return implode("\r\n ", $parts);
    }

    /** Buang CR, LF, dan NUL agar satu nilai tidak bisa menambah header sendiri. */
    private static function oneLine(string $value): string
    {
        return trim(str_replace(["\r", "\n", "\0"], '', $value));
    }
}
