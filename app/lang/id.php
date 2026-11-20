<?php
declare(strict_types=1);

/*
 * Kamus Bahasa Indonesia untuk antarmuka statis (Req 8.5).
 *
 * Berkas ini adalah acuan: setiap kunci yang dipakai view harus ada di sini,
 * karena `I18n::t` memakai teks Bahasa Indonesia sebagai cadangan bila sebuah
 * kunci belum punya terjemahan Inggris (Req 8.7). `app/lang/en.php` memakai
 * kunci yang sama.
 *
 * Isinya hanya teks antarmuka yang ditulis tim: navigasi, label form, tombol,
 * dan keterangan tetap. Tulisan penyintas, pendamping, dan admin tidak masuk
 * kamus dan tidak diterjemahkan (Req 8.5). Pesan validasi yang dibuat di
 * `Validator` dan aturan `app/core/rules/*` juga tidak di sini: pesan itu
 * Bahasa Indonesia sesuai konvensi `app/core/rules/README.md`.
 *
 * Penanda `:nama` diisi `I18n::t($key, $lang, $dict, ['nama' => ...])`.
 *
 * @return array<string,string>
 */

return [
    // ---------- Aksesibilitas dan rangka halaman (Req 8.2) ----------
    'a11y.skip' => 'Lewati ke konten',
    'a11y.menu.open' => 'Buka menu',
    'a11y.menu.close' => 'Tutup menu',
    'a11y.nav.primary' => 'Utama',
    'a11y.nav.mobile' => 'Menu seluler',
    'a11y.home' => 'Haven, ke beranda',
    'a11y.reveal' => 'Tampilkan',
    'a11y.hide' => 'Sembunyikan',
    'a11y.required' => 'wajib diisi',

    // ---------- Navigasi dan kepala halaman ----------
    'nav.report' => 'Lapor',
    'nav.track' => 'Lacak laporan',
    'nav.edu' => 'Edukasi',
    'nav.services' => 'Layanan bantuan',
    'nav.about' => 'Tentang',
    'nav.account' => 'Akun saya',
    'nav.login' => 'Masuk',
    'nav.register' => 'Daftar',
    'nav.cta' => 'Buat laporan',

    // ---------- Pemilih bahasa dan mode (Req 8.6, 8.13) ----------
    'lang.label' => 'Bahasa',
    'lang.switch' => 'Ganti bahasa',
    'lang.id' => 'Bahasa Indonesia',
    'lang.en' => 'English',
    'mode.label' => 'Tampilan',
    'mode.full' => 'Lengkap',
    'mode.lite' => 'Ringan',
    'mode.switch.to_lite' => 'Pakai mode ringan',
    'mode.switch.to_full' => 'Pakai mode lengkap',
    'mode.lite.hint' => 'Mode ringan memuat halaman tanpa gambar dan tanpa pembaruan otomatis, supaya hemat kuota dan tetap jalan di sinyal lemah.',
    'mode.lite.auto' => 'Mode ringan menyala karena perangkatmu meminta hemat data. Kamu bisa menggantinya di sini.',
    'prefs.active' => 'sedang dipakai',

    // ---------- Panggilan darurat (Req 8.12, 8.14) ----------
    'emergency.title' => 'Dalam bahaya sekarang?',
    'emergency.lede' => 'Hubungi nomor di bawah. Nomor ini gratis dan aktif 24 jam.',
    'emergency.call' => 'Telepon :nomor',
    'emergency.112.label' => 'Layanan darurat nasional',
    'emergency.129.label' => 'SAPA 129 (Kementerian PPPA)',

    // ---------- Batas layanan (Req 9.5, 9.6) ----------
    // Diisi `service_limit_html()`: :biasa dan :bahaya adalah batas waktu balasan
    // dari konfigurasi SLA. Satu kalimat ini dipakai beranda, form lapor, panel
    // darurat, halaman tentang, dan kaki halaman.
    'service.limit' => 'Haven bukan layanan darurat. Laporan ditinjau pada jam kerja dan dibalas dalam :biasa. Laporan yang ditandai dalam bahaya diprioritaskan dan dibalas dalam :bahaya. Jika kamu terancam sekarang, hubungi <a href="tel:112">112</a> atau SAPA <a href="tel:129">129</a>.',

    // ---------- Keluar cepat ----------
    'exit.button' => 'Keluar cepat',
    'exit.hint' => 'Tekan Esc dua kali untuk berganti ke halaman cuaca.',

    // ---------- Kaki halaman ----------
    'footer.services' => 'Layanan',
    'footer.learn' => 'Belajar',
    'footer.about' => 'Tentang',
    'footer.privacy' => 'Kerahasiaan',
    'footer.directory' => 'Direktori layanan',
    'footer.about_sdg' => 'Tentang & SDGs 5',
    'footer.safety_tips' => 'Tips menjelajah aman',
    'footer.privacy_note' => 'Kronologi dan pesan dienkripsi. Admin tidak bisa membacanya. Pendampingmu bisa.',
    'footer.credit' => '© :tahun Haven. Ruang aman untuk melapor dan pulih.',

    // ---------- Tombol dan kata umum ----------
    'common.hours' => 'jam',
    'common.optional' => '(opsional)',
    'common.back' => 'Kembali',
    'common.next' => 'Lanjut',
    'common.choose' => 'Pilih',
    'common.copy' => 'Salin',
    'common.download' => 'Unduh sebagai teks',
    'common.errors.title' => 'Ada isian yang perlu diperbaiki.',
    'common.errors.body' => 'Kami membuka isian pertama yang perlu diperbaiki. Isian lain masih tersimpan, tetapi file bukti harus dipilih ulang.',

    // ---------- Form lapor: rangka ----------
    'report.form.aria' => 'Formulir laporan',
    'report.steps.aria' => 'Langkah laporan',
    'report.steps.count' => 'Langkah :sekarang dari :total',
    'report.step1' => 'Apa yang terjadi',
    'report.step2' => 'Ceritakan',
    'report.step3' => 'Waktu & tempat',
    'report.step4' => 'Kontak & kirim',
    'report.submit' => 'Kirim laporan',

    // ---------- Form lapor: langkah 1 ----------
    'report.what.title' => 'Apa yang kamu alami?',
    'report.what.lede' => 'Tiga pertanyaan pertama menentukan siapa yang mendampingi dan seberapa cepat laporanmu ditangani. Jika tidak ada jenis kejadian yang cocok, pilih Belum yakin. Pilihan ini bisa diluruskan nanti.',
    'report.category.legend' => 'Jenis kejadian',
    'report.danger.legend' => 'Apakah kamu dalam bahaya saat ini?',
    'report.danger.yes' => 'Ya, saya butuh bantuan segera',
    'report.danger.no' => 'Tidak saat ini',
    'report.danger.alert.title' => 'Cari bantuan dulu kalau kamu sedang terancam.',
    'report.danger.alert.body' => 'Laporan ini tetap bisa dikirim setelah kamu aman, dan akan muncul paling atas di antrean admin.',
    'report.reporter.legend' => 'Kamu melapor sebagai',
    'report.reporter.victim' => 'Saya korbannya',
    'report.reporter.witness' => 'Saya saksi atau melaporkan untuk orang lain',
    'report.age.legend' => 'Apakah korban berusia di bawah 18 tahun?',
    'report.age.hint' => 'Kasus anak langsung masuk prioritas tertinggi dan ditangani pendamping yang terlatih untuk anak. Pilih "Tidak yakin" kalau kamu tidak tahu pasti.',
    'report.age.yes' => 'Ya, korban di bawah 18 tahun',
    'report.age.no' => 'Tidak, korban 18 tahun atau lebih',
    'report.age.unsure' => 'Tidak yakin',
    'report.victim_contact.label' => 'Kontak korban',
    'report.victim_contact.hint' => 'Nomor, email, atau cara lain menghubungi korban, paling banyak :maks karakter. Isian ini disimpan terenkripsi. Haven tidak menghubungi korban sebelum korban sendiri setuju didampingi, jadi kosongkan saja bila korban belum tahu kamu melapor.',

    // ---------- Form lapor: langkah 2 ----------
    'report.story.title' => 'Apa yang terjadi?',
    'report.story.lede' => 'Tulis dengan bahasamu sendiri. Detail yang berat boleh dilewati. Admin tidak bisa membaca bagian ini, hanya pendamping yang nanti ditugaskan.',
    'report.story.label' => 'Kronologi',
    'report.story.hint' => 'Misalnya: di mana kejadiannya, siapa pelakunya bagimu (tanpa perlu nama), dan apa yang kamu butuhkan sekarang.',
    'report.ongoing.legend' => 'Apakah kejadian ini masih berlangsung?',

    // ---------- Form lapor: langkah 3 ----------
    'report.where.title' => 'Kapan dan di mana?',
    'report.where.lede' => 'Wilayah dipakai untuk memilih pendamping dan layanan terdekat. Semua isian di langkah ini boleh dikosongkan.',
    'report.date.label' => 'Perkiraan tanggal',
    'report.place.label' => 'Jenis tempat',
    'report.place.choose' => 'Pilih jenis tempat',
    'report.province.label' => 'Provinsi',
    'report.province.choose' => 'Pilih provinsi',
    'report.city.label' => 'Kota/kabupaten',
    'report.city.placeholder' => 'Contoh: Banyumas',
    'report.relation.label' => 'Hubunganmu dengan pelaku',

    // ---------- Form lapor: langkah 4 ----------
    'report.contact.title' => 'Lewat mana pendamping boleh menghubungimu?',
    'report.contact.lede' => 'Pesan di Haven selalu aktif. Centang email atau WhatsApp hanya jika tidak ada orang lain yang membukanya.',
    'report.identity.legend' => 'Kirim laporan sebagai',
    'report.identity.anon' => 'Anonim',
    'report.identity.anon.desc' => 'Kamu mendapat kode laporan dan PIN. Simpan keduanya untuk membaca dan membalas pesan pendamping.',
    'report.identity.account' => 'Dengan akun :nama',
    'report.identity.account.desc' => 'Laporan muncul di dasbormu, jadi kamu tidak perlu mengingat kode dan PIN.',
    'report.identity.signin' => 'Ingin laporan masuk ke akun? <a href=":masuk">Masuk</a> atau <a href=":daftar">buat akun</a> dulu. Kamu juga bisa kirim anonim sekarang, lalu menautkannya ke akun nanti dengan kode dan PIN.',
    'report.channel.legend' => 'Cara dihubungi',
    'report.channel.inapp' => 'Pesan aman di Haven (selalu aktif)',
    'report.channel.email' => 'Email',
    'report.channel.wa' => 'WhatsApp',
    'report.email.label' => 'Email aman',
    'report.email.hint' => 'Pendamping hanya mengirim pemberitahuan ke sini, tanpa isi pesan.',
    'report.wa.label' => 'Nomor WhatsApp',
    'report.wa.hint' => 'Tulis nomor yang hanya kamu pegang. Pendamping tidak akan menyebut Haven di kalimat pertama pesannya.',
    'report.files.label' => 'Bukti pendukung',
    'report.files.drop' => 'Pilih file atau seret ke sini',
    'report.files.hint' => 'Tangkapan layar, foto, atau dokumen dalam JPG, PNG, atau PDF. Maksimal :file file, masing-masing 5 MB. Nama file aslinya dienkripsi.',
    'report.review.title' => 'Periksa sebelum mengirim',
    'report.summary.reporter' => 'Melapor sebagai',
    'report.summary.category' => 'Jenis',
    'report.summary.age' => 'Korban di bawah 18 tahun',
    'report.summary.area' => 'Wilayah',
    'report.summary.date' => 'Tanggal',
    'report.summary.relation' => 'Hubungan dengan pelaku',
    'report.summary.story' => 'Kronologi',
    'report.consent.label' => 'Saya paham kronologi ini hanya bisa dibaca pendamping yang ditugaskan, dan saya bisa meminta laporan dihapus lewat pendamping.',

    // ---------- Form lapor: panel samping ----------
    'report.aside.title' => 'Sebelum mulai',
    'report.aside.time' => 'Biasanya 5 sampai 10 menit. Tombol Kembali tidak menghapus isianmu.',
    'report.aside.privacy' => 'Admin hanya melihat jenis kejadian dan wilayah. Kronologi dan kontakmu tidak.',
    'report.aside.exit' => 'Jika ada yang mendekat, tekan Esc dua kali. Halaman langsung berganti ke pencarian cuaca.',

    // ---------- Halaman setelah mengirim ----------
    'sent.title' => 'Laporanmu terkirim.',
    'sent.lede' => 'Laporan ini masuk ke antrean admin. Pendamping akan membalas di halaman laporan, jadi simpan kode dan PIN di bawah. Tanpa keduanya, laporan anonim tidak bisa dibuka lagi.',
    'sent.code' => 'Kode laporan',
    'sent.pin' => 'PIN',
    'sent.copy_code' => 'Salin kode',
    'sent.copy_pin' => 'Salin PIN',
    'sent.pin_once.title' => 'PIN ini hanya muncul sekali.',
    'sent.pin_once.body' => 'Kami hanya menyimpan versi acaknya, jadi kami pun tidak bisa memberitahumu PIN ini lagi. Salin ke tempat yang tidak dibuka orang lain, atau unduh sebagai teks.',
    'sent.open' => 'Buka laporan saya',
    'sent.sla' => 'Pendamping membalas dalam :jam jam.',
    'sent.reachable.title' => 'Bisa kamu hubungi sekarang',
    'sent.reachable.lede' => 'Layanan 24 jam yang nomornya sudah ditelepon tim kami. Tanggal pengecekan tertulis di samping nomor.',
    'sent.next.title' => 'Setelah ini',
    'sent.reading.title' => 'Bacaan untuk sekarang',

    // ---------- Halaman lacak ----------
    'track.title' => 'Buka laporan anonim',
    'track.lede' => 'Masukkan kode dan PIN dari halaman setelah kamu mengirim laporan. Di dalamnya ada status laporan, pesan pendamping, dan jadwal panggilan.',
    'track.code.label' => 'Kode laporan',
    'track.pin.label' => 'PIN',
    'track.submit' => 'Buka laporan',
    'track.lock.note' => 'Setelah 5 kali salah, halaman ini dikunci 15 menit. Ini mencegah orang lain menebak PIN-mu.',
    'track.forgot.title' => 'Lupa kode atau PIN?',
    'track.forgot.body' => 'Kami tidak bisa memulihkannya, karena kami hanya menyimpan versi acak dari PIN. Buat laporan baru dan tulis di kronologi bahwa ini lanjutan dari laporan lama.',
    'track.forgot.cta' => 'Buat laporan baru',
    'track.account.title' => 'Punya akun?',
    'track.account.body' => 'Laporan yang dikirim dengan akun bisa dibuka dari dasbor tanpa kode dan PIN.',
    'track.account.cta' => 'Masuk',
    'track.status.title' => 'Status laporan',
    'track.messages.title' => 'Pesan pendamping',
    'track.close' => 'Tutup sesi laporan di perangkat ini',
    'track.sla' => 'Batas waktu balasan: :jam jam sejak pesan terakhirmu.',
    'track.referral.title' => 'Rujukan untuk kasus anak',
    'track.referral.unverified' => 'Perlu verifikasi',
];
