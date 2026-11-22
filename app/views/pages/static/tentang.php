<main id="konten" tabindex="-1">
    <section class="page-head">
      <div class="container">
        <h1 class="h2">Haven adalah tempat melapor kekerasan seksual tanpa menyebut nama, lalu didampingi sampai ke layanan resmi.</h1>
        <p class="lede">Kami membuatnya untuk tiga alasan orang batal melapor: takut namanya diketahui, tidak tahu lembaga mana yang menangani, dan harus mengurus semuanya sendirian.</p>
      </div>
    </section>

    <section class="section">
      <div class="container proof-grid">
        <?php if (!lite()): ?><div class="media" style="aspect-ratio:4/3;border-radius:6px"><img src="<?= asset('img/counseling.jpg') ?>" alt="Sesi konseling di ruang yang terang" width="1600" height="1067" loading="lazy"></div><?php endif; ?>
        <div>
          <h2 class="h2">Cara Haven bekerja</h2>
          <ul class="proof-points">
            <li><svg class="icon icon-lg" aria-hidden="true"><use href="#i-file"/></svg><div><h3 class="h4">Lapor tanpa nama</h3><p>Kamu menerima kode dan PIN untuk membuka laporanmu lagi. Jika punya akun, laporan juga bisa disimpan di sana. Kronologi dan kontak dienkripsi.</p></div></li>
            <li><svg class="icon icon-lg" aria-hidden="true"><use href="#i-message"/></svg><div><h3 class="h4">Pendampingan</h3><p>Satu pendamping per kasus. Kalian berkirim pesan di dalam Haven, menjadwalkan panggilan, dan pendamping merujukmu ke lembaga resmi bila kamu setuju.</p></div></li>
            <li><svg class="icon icon-lg" aria-hidden="true"><use href="#i-book"/></svg><div><h3 class="h4">Edukasi</h3><p>Tujuh artikel yang menjelaskan UU TPKS dengan contoh sehari-hari, termasuk cara menanggapi teman yang bercerita.</p></div></li>
          </ul>
        </div>
      </div>
    </section>

    <section class="section sdg-band" aria-labelledby="sdg-title">
      <div class="container sdg-grid">
        <div>
          <h2 id="sdg-title" class="h2">Kaitannya dengan SDGs</h2>
          <p class="lede">SDGs adalah 17 tujuan yang disepakati negara anggota PBB pada 2015. Haven paling dekat dengan target 5.2, dan menyentuh dua target lain lewat rujukan.</p>
        </div>
        <ul class="sdg-list">
          <li><span class="sdg-num sdg-5">5</span><div><h3>Kesetaraan gender</h3><p><span class="mono">Target 5.2</span> Menghapus kekerasan terhadap perempuan dan anak perempuan di ruang publik dan pribadi. Haven membuat laporan bisa dikirim tanpa identitas dan memastikan setiap laporan punya pendamping.</p></div></li>
          <li><span class="sdg-num sdg-16">16</span><div><h3>Perdamaian, keadilan, dan kelembagaan yang tangguh</h3><p><span class="mono">Target 16.1 &amp; 16.3</span> Akses keadilan. Pendamping bisa merujuk ke lembaga bantuan hukum dan unit PPA kepolisian.</p></div></li>
          <li><span class="sdg-num sdg-3">3</span><div><h3>Kehidupan sehat dan sejahtera</h3><p><span class="mono">Target 3.4</span> Kesehatan mental. Pendamping bisa merujuk ke psikolog atau UPTD PPA yang punya layanan konseling.</p></div></li>
        </ul>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="help-head">
          <h2 class="h2">Batas layanan Haven</h2>
          <p class="lede">Haven mendampingi lewat pesan dan rujukan ke lembaga resmi. Haven tidak menggantikan polisi, rumah sakit, atau hotline yang berjaga sepanjang hari.</p>
        </div>
        <div class="alert alert-info" style="margin-top:48px">
          <svg class="icon" aria-hidden="true"><use href="#i-info"/></svg>
          <div><?= service_limit_html() ?></div>
        </div>
      </div>
    </section>
  </main>
