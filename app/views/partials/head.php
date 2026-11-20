<?php
/**
 * Isi `<head>` untuk kedua layout.
 *
 * Mode ringan melewatkan font web: huruf diambil dari perangkat, jadi tidak ada
 * permintaan ke domain lain dan tidak ada berkas font yang diunduh (Req 8.8, 8.9).
 *
 * Blok `<noscript>` membuat formulir berlangkah tetap bisa dipakai saat
 * JavaScript mati: semua langkah terbuka, tombol Lanjut dan Kembali
 * disembunyikan, dan tombol Kirim selalu terlihat (Req 8.11). Gaya ini hanya
 * berlaku ketika browser benar-benar tidak menjalankan skrip, jadi tampilan
 * berlangkah tidak pernah berkedip saat JavaScript hidup.
 *
 * @var string|null $title  @var string|null $description
 */
$liteHead = lite();
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="referrer" content="no-referrer">
<meta name="robots" content="noindex">
<meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
<meta name="theme-color" content="#FFC907">
<link rel="icon" href="<?= e(url('/favicon.svg')) ?>" type="image/svg+xml">
<title><?= e($title ?? 'Haven | Ruang aman untuk melapor dan pulih') ?></title>
<meta name="description" content="<?= e($description ?? 'Haven membantu penyintas kekerasan seksual melapor dengan aman, mendapat pendamping, dan menemukan layanan bantuan.') ?>">
<?php if (!$liteHead): ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Anonymous+Pro:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/fonts.css') ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= asset('css/styles.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<script src="<?= asset('js/app.js') ?>" defer></script>
<noscript><style>
  [data-step][hidden] { display: block !important; }
  [data-show-when][hidden] { display: block !important; }
  .btn[data-submit][hidden] { display: inline-flex !important; }
  .stepper, .step-count, [data-next], [data-prev] { display: none !important; }
</style></noscript>
