<?php
/**
 * Panel panggilan darurat untuk form lapor dan halaman lacak (Req 8.12, 8.14).
 *
 * Selalu dua nomor, 112 lalu 129, dalam setiap bahasa dan kedua mode tampilan.
 * Tidak ada tombol "tampilkan" dan tidak ada JavaScript: nomornya langsung
 * terbaca begitu halaman terbuka. Nama lembaga diambil dari layanan
 * terverifikasi bila ada; bila tidak, label bawaan dari kamus yang dipakai,
 * jadi daftar ini tidak pernah kosong walau direktori layanan masih kosong.
 *
 * Database yang sedang bermasalah pun tidak boleh menghilangkan nomor darurat,
 * karena itu kegagalan kueri diperlakukan sebagai "tidak ada layanan cocok".
 */
try {
    $emergencyServices = Service::emergencyLines();
} catch (Throwable) {
    $emergencyServices = [];
}
$emergencyOptions = Emergency::options($emergencyServices, lang(), lang_dict());
?>
<aside class="emergency" aria-labelledby="darurat-judul">
  <h2 class="emergency-title" id="darurat-judul"><?= icon('phone') ?><?= te('emergency.title') ?></h2>
  <p class="emergency-lede"><?= te('emergency.lede') ?></p>
  <ul class="emergency-list">
    <?php foreach ($emergencyOptions as $option): ?>
      <li>
        <a class="emergency-call" href="tel:<?= e($option['number']) ?>">
          <span class="emergency-name"><?= e($option['label']) ?></span>
          <span class="emergency-num mono"><?= e($option['number']) ?></span>
          <span class="sr-only"><?= te('emergency.call', ['nomor' => $option['number']]) ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
  <p class="emergency-note"><?= service_limit_html() ?></p>
</aside>
