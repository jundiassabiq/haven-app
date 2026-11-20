<?php
/**
 * Beranda. Mode ringan melewatkan gambar sampul: latar gelap `.hero-media` tetap
 * ada, jadi teks hero tetap terbaca tanpa mengunduh fotonya (Req 8.8).
 *
 * @var array $numbers  @var array $articles
 */
$lite = lite();
?>
<main id="konten" tabindex="-1">
    <!-- HERO -->
    <section class="hero" aria-labelledby="hero-title">
      <div class="media hero-media"><?php if (!$lite): ?><img src="<?= asset('img/support-circle.jpg') ?>" alt="" width="2400" height="1600" fetchpriority="high"><?php endif; ?></div>
      <div class="hero-shade" aria-hidden="true"></div>
      <div class="container hero-inner">
        <div class="hero-copy">
          <h1 id="hero-title" class="display">Laporkan kekerasan seksual tanpa menyebut nama.</h1>
          <p class="hero-lede">Isi empat langkah, simpan kode dan PIN yang kamu terima, lalu tunggu pendamping membalas lewat pesan. Kamu tidak perlu akun, dan admin tidak bisa membaca ceritamu.</p>
          <div class="hero-actions">
            <a class="btn btn-yellow btn-lg" href="<?= e(url('/lapor')) ?>">Buat laporan <svg class="icon" aria-hidden="true"><use href="#i-arrow-right"/></svg></a>
            <a class="btn btn-ghost-light btn-lg" href="<?= e(url('/lacak')) ?>">Lacak laporan</a>
          </div>
        </div>

        <aside class="help-card" aria-labelledby="help-title">
          <h2 id="help-title">Butuh bantuan sekarang?</h2>
          <p><?= service_limit_html() ?></p>
          <div class="hotlines">
            <a class="hotline" href="tel:112">
              <span class="hotline-name">Darurat nasional</span>
              <span class="hotline-desc">Polisi, ambulans, pemadam</span>
              <span class="hotline-num">112</span>
            </a>
            <a class="hotline" href="tel:129">
              <span class="hotline-name">SAPA Kementerian PPPA</span>
              <span class="hotline-desc">Pengaduan kekerasan pada perempuan &amp; anak</span>
              <span class="hotline-num">129</span>
            </a>
          </div>
          <a class="btn btn-ink btn-block" href="<?= e(url('/layanan')) ?>">Cari layanan terdekat</a>
        </aside>
      </div>
    </section>

    <!-- CARA KERJA -->
    <section class="section" aria-labelledby="how-title">
      <div class="container">
        <div class="help-head">
          <h2 id="how-title" class="h2">Empat tahap, dan kamu boleh berhenti di mana saja.</h2>
          <p class="lede">Kamu tidak wajib menyelesaikan semua tahap. Banyak orang hanya ingin bercerita dan didengar, dan itu cukup.</p>
        </div>
        <ol class="steps">
          <li>
            <span class="step-n">01</span>
            <h3>Ceritakan</h3>
            <p>Pilih jenis kejadian, tulis kronologi, isi wilayah jika mau, lalu kirim. Nama asli tidak diminta.</p>
          </li>
          <li>
            <span class="step-n">02</span>
            <h3>Simpan kode</h3>
            <p>Kode dan PIN hanya muncul sekali. Laporan anonim tidak bisa dibuka tanpa keduanya, termasuk oleh kami.</p>
          </li>
          <li>
            <span class="step-n">03</span>
            <h3>Didampingi</h3>
            <p>Admin menugaskan satu pendamping. Ia membalas lewat pesan di Haven dan bisa membuka jadwal panggilan.</p>
          </li>
          <li>
            <span class="step-n">04</span>
            <h3>Dirujuk</h3>
            <p>Jika kamu setuju, pendamping memilihkan layanan seperti UPTD PPA, rumah sakit, atau bantuan hukum.</p>
          </li>
        </ol>
      </div>
    </section>

    <!-- KERAHASIAAN -->
    <section class="section proof" style="background: var(--cream)" aria-labelledby="safe-title">
      <div class="container proof-grid">
        <div class="proof-copy">
          <h2 id="safe-title" class="h2">Admin tidak bisa membaca kronologimu.</h2>
          <p class="lede">Admin hanya melihat jenis kejadian dan wilayah, cukup untuk memilih pendamping di dekatmu. Kronologi, kontak, dan pesan hanya dibuka pendamping yang ditugaskan.</p>
          <ul class="proof-points">
            <li>
              <svg class="icon icon-lg" aria-hidden="true"><use href="#i-lock"/></svg>
              <div>
                <h3 class="h4">Disimpan dalam bentuk sandi</h3>
                <p>Kronologi, kontak, catatan pendamping, dan pesan dienkripsi dengan AES-256-GCM. Orang yang membuka database tanpa kuncinya hanya melihat deretan huruf acak.</p>
              </div>
            </li>
            <li>
              <svg class="icon icon-lg" aria-hidden="true"><use href="#i-exit"/></svg>
              <div>
                <h3 class="h4">Keluar cepat kapan saja</h3>
                <p>Tombol <strong>Keluar cepat</strong> di pojok kanan bawah, atau Esc dua kali, langsung membuka pencarian cuaca. Tombol Kembali tidak mengembalikanmu ke Haven.</p>
              </div>
            </li>
            <li>
              <svg class="icon icon-lg" aria-hidden="true"><use href="#i-shield-check"/></svg>
              <div>
                <h3 class="h4">Setiap akses tercatat</h3>
                <p>Setiap kali pendamping atau admin membuka laporan, audit log menyimpan nama, waktu, dan alamat IP-nya. Percobaan akses yang ditolak juga tercatat.</p>
              </div>
            </li>
          </ul>
          <a class="text-link" href="<?= e(url('/tips-aman')) ?>">Tips menjelajah dengan aman <svg class="icon" aria-hidden="true"><use href="#i-arrow-right"/></svg></a>
        </div>

        <figure class="proof-card" aria-label="Contoh laporan seperti yang tersimpan di database">
          <?php if (!$lite): ?><div class="media proof-media"><img src="<?= asset('img/holding-hands.jpg') ?>" alt="" width="1000" height="1500" loading="lazy"></div><?php endif; ?>
          <figcaption class="mock-report">
            <div class="row-between">
              <span class="case-code" style="font-size:1.125rem">HV-7Q4K2M</span>
              <span class="badge" data-status="didampingi">Didampingi</span>
            </div>
            <dl class="kv">
              <div><dt>Jenis</dt><dd>Pelecehan nonfisik</dd></div>
              <div><dt>Wilayah</dt><dd>Banyumas</dd></div>
              <div class="full"><dt>Kronologi (yang tersimpan)</dt><dd class="cipher">q8Jf2Lr+0bW1kT9xVn3aE7m4ZcP0yH6s/uD2gQe5RkA=â€¦ </dd></div>
            </dl>
          </figcaption>
        </figure>
      </div>
    </section>

    <!-- KENALI -->
    <section class="section why" style="background: var(--paper)" aria-labelledby="learn-title">
      <div class="container">
        <div class="why-head">
          <h2 id="learn-title" class="h2">Yang kamu alami mungkin punya nama di undang-undang.</h2>
          <p class="lede">UU No. 12 Tahun 2022 (UU TPKS) mengakui sembilan bentuk kekerasan seksual. Komentar di grup kelas dan ancaman menyebar foto termasuk di dalamnya.</p>
        </div>
        <div class="tabs" data-tabs>
          <div class="tab-list" role="tablist" aria-label="Topik edukasi">
            <button role="tab" id="t-kenali" aria-controls="p-kenali" aria-selected="true">Bentuk kekerasan</button>
            <button role="tab" id="t-hak" aria-controls="p-hak" aria-selected="false" tabindex="-1">Hak korban</button>
            <button role="tab" id="t-teman" aria-controls="p-teman" aria-selected="false" tabindex="-1">Mendampingi teman</button>
            <button role="tab" id="t-digital" aria-controls="p-digital" aria-selected="false" tabindex="-1">Keamanan digital</button>
          </div>
          <div class="tab-panel" role="tabpanel" id="p-kenali" aria-labelledby="t-kenali" style="background:var(--cream)">
            <?php if (!$lite): ?><div class="media tab-media"><img src="<?= asset('img/reaching-hands.jpg') ?>" alt="Dua tangan saling meraih" width="1600" height="1067" loading="lazy"></div><?php endif; ?>
            <div class="tab-copy">
              <h3 class="h3">Komentar dan pesan juga bisa dilaporkan.</h3>
              <p>Pelecehan nonfisik, seperti siulan, isyarat, atau lelucon seksual yang terus diulang setelah diminta berhenti, adalah salah satu dari sembilan bentuk dalam UU TPKS.</p>
              <a class="text-link" href="<?= e(url('/edukasi/sembilan-bentuk-kekerasan-seksual')) ?>">Baca kesembilan bentuknya <svg class="icon" aria-hidden="true"><use href="#i-arrow-right"/></svg></a>
            </div>
          </div>
          <div class="tab-panel" role="tabpanel" id="p-hak" aria-labelledby="t-hak" hidden style="background:var(--cream)">
            <?php if (!$lite): ?><div class="media tab-media"><img src="<?= asset('img/counseling.jpg') ?>" alt="Seorang perempuan berbicara dengan konselor" width="1600" height="1067" loading="lazy"></div><?php endif; ?>
            <div class="tab-copy">
              <h3 class="h3">Kamu boleh minta layanan tanpa melapor ke polisi.</h3>
              <p>Pemeriksaan kesehatan, konseling, dan pendampingan hukum adalah hak korban menurut UU TPKS. Laporan ke polisi tidak menjadi syaratnya.</p>
              <a class="text-link" href="<?= e(url('/edukasi/hak-korban')) ?>">Baca daftar hak korban <svg class="icon" aria-hidden="true"><use href="#i-arrow-right"/></svg></a>
            </div>
          </div>
          <div class="tab-panel" role="tabpanel" id="p-teman" aria-labelledby="t-teman" hidden style="background:var(--cream)">
            <?php if (!$lite): ?><div class="media tab-media"><img src="<?= asset('img/holding-hands.jpg') ?>" alt="Dua orang saling menggenggam tangan" width="1000" height="1500" loading="lazy"></div><?php endif; ?>
            <div class="tab-copy">
              <h3 class="h3">Temanmu bercerita? Jangan tanya kenapa ia tidak melawan.</h3>
              <p>Pertanyaan seperti itu terdengar menyalahkan. Artikel ini berisi kalimat yang sebaiknya kamu ucapkan dan yang sebaiknya kamu hindari.</p>
              <a class="text-link" href="<?= e(url('/edukasi/cara-mendampingi-teman')) ?>">Baca cara mendampingi teman <svg class="icon" aria-hidden="true"><use href="#i-arrow-right"/></svg></a>
            </div>
          </div>
          <div class="tab-panel" role="tabpanel" id="p-digital" aria-labelledby="t-digital" hidden style="background:var(--cream)">
            <?php if (!$lite): ?><div class="media tab-media"><img src="<?= asset('img/phone.jpg') ?>" alt="Seorang perempuan berhijab memegang ponsel" width="1600" height="1067" loading="lazy"></div><?php endif; ?>
            <div class="tab-copy">
              <h3 class="h3">Diancam fotomu akan disebar?</h3>
              <p>Jangan turuti permintaannya. Ambil tangkapan layar yang memperlihatkan nama akun dan tanggal, lalu laporkan. Ancaman seperti ini termasuk kekerasan seksual berbasis elektronik.</p>
              <a class="text-link" href="<?= e(url('/edukasi/kekerasan-berbasis-elektronik')) ?>">Langkah mengamankan bukti dan akun <svg class="icon" aria-hidden="true"><use href="#i-arrow-right"/></svg></a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ANGKA -->
    <section class="section impact" aria-labelledby="impact-title">
      <div class="container">
        <h2 id="impact-title" class="h2 impact-title">Laporan dan pendampingan di Haven sejauh ini</h2>
        <dl class="stats">
          <div class="stat"><dt>Laporan diterima</dt><dd class="mono" data-count="<?= (int) $numbers['reports'] ?>"><?= number_format($numbers['reports'], 0, ',', '.') ?></dd></div>
          <div class="stat"><dt>Pendamping aktif</dt><dd class="mono" data-count="<?= (int) $numbers['companions'] ?>"><?= number_format($numbers['companions'], 0, ',', '.') ?></dd></div>
          <div class="stat"><dt>Sesi pendampingan selesai</dt><dd class="mono" data-count="<?= (int) $numbers['sessions'] ?>"><?= number_format($numbers['sessions'], 0, ',', '.') ?></dd></div>
        </dl>
        <p class="small" style="margin-top:28px">Dihitung langsung dari database setiap kali halaman ini dibuka.</p>
      </div>
    </section>

    <!-- EDUKASI TERBARU -->
    <section class="section" aria-labelledby="news-title">
      <div class="container">
        <div class="row-between" style="margin-bottom:48px">
          <h2 id="news-title" class="h2">Artikel terbaru</h2>
          <a class="text-link" href="<?= e(url('/edukasi')) ?>">Semua artikel <svg class="icon" aria-hidden="true"><use href="#i-arrow-right"/></svg></a>
        </div>
        <?php if ($articles): ?>
          <ul class="article-grid">
            <?php foreach ($articles as $a): include APP_PATH . '/views/partials/article-card.php'; endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="muted">Belum ada artikel yang terbit.</p>
        <?php endif; ?>
      </div>
    </section>

    <!-- DIREKTORI SINGKAT -->
    <section class="section" style="background:var(--cream)" aria-labelledby="dir-title">
      <div class="container grid-2" style="align-items:start">
        <div>
          <h2 id="dir-title" class="h2">Lembaga yang bisa kamu hubungi langsung.</h2>
          <p class="lede" style="margin-top:18px">UPTD PPA, rumah sakit, lembaga bantuan hukum, dan unit PPA kepolisian. Nomor telepon hanya ditampilkan setelah tim mengeceknya ke lembaga tersebut.</p>
          <a class="btn btn-ink" href="<?= e(url('/layanan')) ?>" style="margin-top:32px">Cari layanan di wilayahmu</a>
        </div>
        <?php if (!$lite): ?>
          <div data-directory="public" data-limit="3" data-endpoint="<?= e(url('/api/layanan')) ?>">
            <ul class="service-list" data-directory-list></ul>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- SDGs -->
    <section class="section sdg-band" aria-labelledby="sdg-title">
      <div class="container sdg-grid">
        <div>
          <h2 id="sdg-title" class="h2">Haven dibuat untuk SDGs 5, target 5.2.</h2>
          <p class="lede">Target itu meminta negara menghapus kekerasan terhadap perempuan dan anak perempuan, di ruang publik maupun pribadi. Haven mengerjakan bagian kecilnya: membuat laporan lebih mudah dikirim dan tidak berhenti di kotak masuk.</p>
          <a class="text-link" href="<?= e(url('/tentang')) ?>" style="margin-top:32px;color:var(--paper)">Tentang Haven <svg class="icon" aria-hidden="true"><use href="#i-arrow-right"/></svg></a>
        </div>
        <ul class="sdg-list">
          <li><span class="sdg-num sdg-5">5</span><div><h3>Kesetaraan gender</h3><p><span class="mono">Target 5.2</span> Laporan anonim, pendamping per kasus, dan artikel tentang kekerasan seksual.</p></div></li>
          <li><span class="sdg-num sdg-16">16</span><div><h3>Perdamaian, keadilan, dan kelembagaan yang tangguh</h3><p><span class="mono">Target 16.3</span> Rujukan ke lembaga bantuan hukum dan unit PPA kepolisian bila korban ingin menempuh jalur hukum.</p></div></li>
          <li><span class="sdg-num sdg-3">3</span><div><h3>Kehidupan sehat dan sejahtera</h3><p><span class="mono">Target 3.4</span> Rujukan ke psikolog dan UPTD PPA untuk dukungan kesehatan mental.</p></div></li>
        </ul>
      </div>
    </section>
  </main>
