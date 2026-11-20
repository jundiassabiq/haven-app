<?php
/** @var int $status  @var string $message  @var string $ref  @var Throwable|null $debug */
$copy = [
    403 => ['Kamu tidak punya akses ke halaman ini.', 'Laporan hanya bisa dibuka oleh pemiliknya dan pendamping yang ditugaskan. Percobaan ini tercatat di audit log.'],
    404 => ['Halaman ini tidak ditemukan.', 'Tautannya mungkin salah ketik, atau artikelnya sudah dihapus. Laporanmu tetap aman.'],
    405 => ['Cara membuka halaman ini tidak didukung.', 'Kembali ke halaman sebelumnya dan gunakan tombol yang tersedia.'],
    419 => ['Formulir ini sudah kedaluwarsa.', 'Formulir ini dibuka terlalu lama, atau kamu keluar dan masuk lagi di tab lain. Kembali, muat ulang halamannya, lalu kirim lagi.'],
    429 => ['Terlalu banyak percobaan.', 'Tunggu beberapa menit sebelum mencoba lagi.'],
    413 => ['Kiriman terlalu besar.', 'Kurangi jumlah atau ukuran file bukti, lalu kirim lagi.'],
    500 => ['Ada kendala di sisi kami.', 'Isian yang belum terkirim tidak tersimpan. Coba lagi dalam beberapa menit, dan sebutkan kode referensi di bawah jika kamu menghubungi tim.'],
];
[$heading, $body] = $copy[$status] ?? $copy[500];
?>
<main id="konten" tabindex="-1" class="error-page">
  <div class="inner">
    <p class="error-code" aria-hidden="true"><?= (int) $status ?></p>
    <h1 class="h2"><?= e($heading) ?></h1>
    <p><?= e($message !== '' ? $message : $body) ?></p>
    <?php if ($status >= 500): ?>
      <p>Jika kamu dalam bahaya, jangan menunggu: hubungi <a href="tel:112">112</a> atau SAPA <a href="tel:129">129</a>.</p>
    <?php endif; ?>
    <?php if ($ref !== ''): ?><p class="mono small muted">Kode referensi: <?= e($ref) ?></p><?php endif; ?>
    <div class="row">
      <?php if ($status === 403): ?>
        <a class="btn btn-ink" href="<?= e(url('/masuk')) ?>">Masuk dengan akun lain</a>
      <?php elseif ($status === 419 || $status === 413 || $status >= 500): ?>
        <button class="btn btn-ink" type="button" data-back>Kembali ke halaman sebelumnya</button>
      <?php else: ?>
        <a class="btn btn-ink" href="<?= e(url('/')) ?>">Ke beranda</a>
      <?php endif; ?>
      <a class="btn btn-ghost" href="<?= e(url('/lacak')) ?>">Lacak laporan</a>
    </div>
    <?php if ($debug !== null): ?>
      <pre class="small" style="text-align:left;white-space:pre-wrap;max-width:90vw;margin-top:24px"><?= e($debug::class . ': ' . $debug->getMessage() . "\n" . $debug->getFile() . ':' . $debug->getLine()) ?></pre>
    <?php endif; ?>
  </div>
</main>
