-- Haven education content. Original text written for this project; it refers to, but does not copy,
-- the laws named in each article. Import after seed.sql.
--
-- The whole file is idempotent and can be imported any number of times:
--   * categories use INSERT IGNORE,
--   * articles use INSERT ... ON DUPLICATE KEY UPDATE keyed on the unique slug (ids are assigned by MySQL),
--   * everything runs inside one transaction, so a failing statement leaves existing articles untouched
--     (SqlFile::run issues the rollback and reports which statement failed).
USE haven_v2;

START TRANSACTION;

INSERT IGNORE INTO article_categories (id, slug, name) VALUES
 (1, 'mengenali', 'Mengenali'),
 (2, 'setelah', 'Setelah kejadian'),
 (3, 'hak', 'Hak korban'),
 (4, 'mendampingi', 'Mendampingi'),
 (5, 'digital', 'Keamanan digital'),
 (6, 'kesetaraan', 'Kesetaraan gender'),
 (7, 'kepemimpinan', 'Kepemimpinan perempuan'),
 (8, 'sekutu', 'Laki-laki sebagai sekutu'),
 (9, 'kerja', 'Hak di tempat kerja');

INSERT INTO articles (category_id, author_id, title, slug, excerpt, body, sources, cover_path, cover_alt, read_minutes, status, published_at, created_at, updated_at) VALUES
(1, 1, 'Sembilan bentuk kekerasan seksual menurut UU TPKS', 'sembilan-bentuk-kekerasan-seksual',
 'Mengenali bentuknya adalah langkah pertama untuk berkata "ini tidak benar".',
'Kekerasan seksual tidak selalu berupa sentuhan, dan tidak selalu dilakukan orang asing. Pada 2022, Indonesia mengesahkan Undang-Undang Nomor 12 Tahun 2022 tentang Tindak Pidana Kekerasan Seksual, yang sering disingkat UU TPKS. Untuk pertama kalinya berbagai bentuk kekerasan seksual diakui dengan jelas, termasuk yang dulu sering dianggap "bercanda" atau "urusan pribadi".

Berikut sembilan bentuk yang disebut dalam undang-undang itu, dijelaskan dengan bahasa sehari-hari.

## 1. Pelecehan seksual nonfisik
Ucapan, isyarat, tulisan, atau perbuatan bernuansa seksual yang ditujukan pada tubuh atau seksualitas seseorang dan membuatnya merasa direndahkan. Contohnya siulan dan komentar tentang tubuh di jalan, atau lelucon seksual yang terus diulang meski sudah diminta berhenti.

## 2. Pelecehan seksual fisik
Sentuhan atau kontak fisik bernuansa seksual tanpa persetujuan, misalnya meraba di kendaraan umum atau sengaja menempelkan tubuh.

## 3. Pemaksaan kontrasepsi
Memaksa seseorang memakai alat kontrasepsi dengan ancaman, tipu daya, atau penyalahgunaan kekuasaan, sehingga ia kehilangan kendali atas fungsi reproduksinya untuk sementara.

## 4. Pemaksaan sterilisasi
Seperti di atas, tetapi akibatnya permanen: seseorang dibuat tidak bisa memiliki keturunan tanpa persetujuan yang bebas.

## 5. Pemaksaan perkawinan
Memaksa seseorang menikah, termasuk menikahkan korban dengan pelaku dengan alasan "menutup aib". Perkawinan anak juga termasuk di dalamnya.

## 6. Penyiksaan seksual
Perbuatan seksual yang menyakiti atau merendahkan, dilakukan untuk mengintimidasi, menghukum, atau memaksa pengakuan dari seseorang.

## 7. Eksploitasi seksual
Memanfaatkan tubuh atau seksualitas seseorang untuk keuntungan, dengan memanfaatkan kerentanan, ketergantungan, atau kepercayaannya.

## 8. Perbudakan seksual
Membatasi kebebasan seseorang dan menjadikannya tidak berdaya untuk dieksploitasi secara seksual.

## 9. Kekerasan seksual berbasis elektronik
Merekam atau mengambil gambar bermuatan seksual tanpa izin, menyebarkan konten seksual seseorang tanpa persetujuannya, atau menguntit lewat perangkat elektronik untuk tujuan seksual. Ini tetap kekerasan meskipun foto atau videonya awalnya dibuat secara sukarela.

> Bukan salahmu
> Kesalahan selalu ada pada pelaku. Pakaian, tempat, waktu, atau hubunganmu dengan pelaku tidak membuat kekerasan itu menjadi salahmu.

## Kalau kamu mengenali salah satunya
Kamu tidak harus langsung melapor ke polisi. Kamu boleh mulai dengan bercerita ke orang yang kamu percaya, atau [membuat laporan anonim di Haven](/lapor) untuk mendapat pendamping. Pendamping bisa membantumu memahami pilihan, termasuk layanan kesehatan, psikologis, dan bantuan hukum.

- [Cari layanan bantuan terdekat](/layanan)',
'UU RI No. 12 Tahun 2022 tentang Tindak Pidana Kekerasan Seksual, Pasal 4 ayat (1), JDIH BPK | https://peraturan.bpk.go.id/
Komnas Perempuan, publikasi dan Catatan Tahunan | https://komnasperempuan.go.id/
Kementerian Pemberdayaan Perempuan dan Perlindungan Anak | https://www.kemenpppa.go.id/',
 'img/reaching-hands.jpg', 'Dua tangan saling meraih di latar abu-abu', 6, 'terbit', '2026-11-10 09:00:00', '2026-11-10 09:00:00', '2026-11-10 09:00:00'),

(2, 1, 'Langkah yang bisa kamu ambil setelah kejadian', 'langkah-setelah-kejadian',
 'Tidak ada urutan yang wajib. Ini pilihan-pilihan yang bisa membantu, termasuk menjaga bukti.',
'Tidak ada urutan langkah yang wajib setelah kekerasan seksual. Bagian di bawah ini adalah pilihan. Beberapa, seperti pemeriksaan kesehatan, lebih berguna bila dilakukan dalam beberapa hari pertama.

## Pastikan kamu aman dulu
Jika pelaku masih di dekatmu atau kamu merasa terancam, pindah ke tempat yang lebih aman dan hubungi **112**. Kabari satu orang yang kamu percaya tentang di mana kamu berada.

## Periksakan kesehatanmu sesegera mungkin
Tenaga kesehatan bisa memeriksa luka, memberi obat pencegahan kehamilan, dan mencegah sebagian infeksi menular seksual. Beberapa obat ini paling efektif bila diberikan dalam beberapa hari pertama, jadi lebih cepat lebih baik. Kamu boleh menolak pemeriksaan apa pun yang tidak kamu inginkan.

Jika kamu mungkin ingin menempuh jalur hukum, sampaikan kepada petugas. Rumah sakit dapat membuat visum, yaitu catatan medis yang bisa dipakai sebagai bukti.

## Simpan bukti, meski belum yakin akan melapor
- Jangan hapus pesan, foto, atau riwayat panggilan. Ambil tangkapan layar yang memperlihatkan nama akun dan tanggal.
- Simpan pakaian yang dikenakan dalam kantong kertas, dan sebaiknya tidak dicuci dulu.
- Tulis kronologi dengan kata-katamu sendiri selagi ingatan masih segar: kapan, di mana, siapa saja yang ada.

Menyimpan bukti tidak mewajibkanmu melapor. Ini hanya menjaga pilihanmu tetap terbuka.

## Cari teman bicara
Kamu bisa bercerita ke orang yang kamu percaya, menelepon SAPA 129, atau [membuat laporan di Haven](/lapor) dan menunggu pendamping membalas lewat pesan.

## Melapor ke polisi tidak wajib
Melapor ke polisi bisa dilakukan lewat unit Perlindungan Perempuan dan Anak (PPA). Lembaga bantuan hukum dapat mendampingi tanpa biaya. Jika kamu belum siap, itu juga keputusan yang sah.

> Reaksi setiap orang berbeda
> Ada yang marah, ada yang takut, ada yang mati rasa, dan ada yang merasa biasa saja selama berhari-hari. Semua itu reaksi yang umum. Jika susah tidur atau sulit beraktivitas berlanjut lebih dari beberapa minggu, psikolog bisa membantu.',
'UU RI No. 12 Tahun 2022 tentang Tindak Pidana Kekerasan Seksual, JDIH BPK | https://peraturan.bpk.go.id/
Kementerian Pemberdayaan Perempuan dan Perlindungan Anak, layanan SAPA 129 | https://www.kemenpppa.go.id/
Komnas Perempuan | https://komnasperempuan.go.id/',
 'img/sunset.jpg', 'Anak meraih matahari terbenam', 5, 'terbit', '2026-11-08 09:00:00', '2026-11-08 09:00:00', '2026-11-08 09:00:00'),

(3, 1, 'Hak-hakmu sebagai korban', 'hak-korban',
 'Penanganan, pelindungan, dan pemulihan adalah hakmu, bukan kebaikan hati orang lain.',
'UU TPKS menegaskan bahwa korban kekerasan seksual punya hak yang harus dipenuhi negara. Hak itu dikelompokkan menjadi tiga: hak atas **penanganan**, hak atas **pelindungan**, dan hak atas **pemulihan**. Kamu tidak perlu menunggu proses hukum selesai untuk mendapatkannya.

## Hak atas penanganan
- Mendapat informasi tentang hak-hakmu dan perkembangan kasusmu.
- Mendapat layanan kesehatan, termasuk pemeriksaan dan pengobatan.
- Mendapat pendampingan hukum dan psikologis sejak awal.
- Diperiksa oleh petugas yang tidak menghakimi dan tidak menanyakan hal yang merendahkan.

## Hak atas pelindungan
- Kerahasiaan identitasmu dijaga.
- Dilindungi dari ancaman atau kekerasan lanjutan, termasuk dari pelaku dan keluarganya.
- Tidak kehilangan pekerjaan, pendidikan, atau hak lain karena melapor.

Lembaga Perlindungan Saksi dan Korban (LPSK) dapat memberikan perlindungan jika kamu merasa terancam.

## Hak atas pemulihan
- Dukungan psikologis dan sosial, misalnya konseling dan kelompok dukungan.
- Restitusi, yaitu ganti kerugian dari pelaku atas kerugian yang kamu alami.
- Bantuan untuk kembali beraktivitas, seperti sekolah atau bekerja.

## Di mana hak ini bisa diminta
UPTD Perlindungan Perempuan dan Anak (UPTD PPA) di kabupaten, kota, atau provinsi adalah pintu layanan terpadu. Kamu bisa datang langsung, atau minta pendamping Haven membantu menghubungkan. Lihat [direktori layanan](/layanan).

> Kamu tidak harus melapor ke polisi dulu
> Layanan kesehatan, psikologis, dan pendampingan tetap bisa kamu akses walaupun belum atau tidak ingin menempuh jalur hukum.',
'UU RI No. 12 Tahun 2022 tentang Tindak Pidana Kekerasan Seksual, ketentuan hak korban, JDIH BPK | https://peraturan.bpk.go.id/
Lembaga Perlindungan Saksi dan Korban | https://www.lpsk.go.id/
Kementerian Pemberdayaan Perempuan dan Perlindungan Anak | https://www.kemenpppa.go.id/',
 'img/counseling.jpg', 'Seorang perempuan berbicara dengan konselor', 5, 'terbit', '2026-11-05 09:00:00', '2026-11-05 09:00:00', '2026-11-05 09:00:00'),

(4, 1, 'Cara mendampingi teman yang bercerita', 'cara-mendampingi-teman',
 'Apa yang sebaiknya diucapkan, dan apa yang sebaiknya tidak.',
'Temanmu tidak menunggu solusi darimu. Biasanya ia ingin tahu satu hal: kamu percaya atau tidak. Bagian di bawah berisi kalimat yang membantu dan kalimat yang sebaiknya tidak diucapkan.

## Dengarkan dulu
Beri ruang dan waktu. Biarkan temanmu bercerita dengan caranya sendiri, tanpa dipotong dan tanpa didesak menceritakan detail yang tidak ingin ia ceritakan.

## Kalimat yang membantu
- "Aku percaya kamu."
- "Ini bukan salahmu."
- "Terima kasih sudah mau cerita ke aku."
- "Apa yang kamu butuhkan sekarang?"

## Kalimat yang sebaiknya dihindari
- "Kenapa kamu tidak melawan?"
- "Kamu pakai baju apa waktu itu?"
- "Kok baru cerita sekarang?"
- "Sudah, lupakan saja."

Pertanyaan seperti itu, meski maksudnya baik, bisa terdengar menyalahkan dan membuat korban menyesal telah bercerita.

## Hormati keputusannya
Tawarkan pilihan, jangan memaksa. Kamu bisa menyebutkan layanan yang tersedia, menawarkan menemani ke rumah sakit, atau membantu [membuat laporan](/lapor). Keputusan tetap ada di tangannya, termasuk keputusan untuk tidak melapor.

## Jaga ceritanya
Jangan menceritakan kembali kepada orang lain atau mengunggahnya ke media sosial tanpa izin, walaupun niatmu membantu. Menyebarkan cerita bisa membuatnya kembali terluka dan berisiko bagi keselamatannya.

## Jaga dirimu juga
Mendengar cerita kekerasan bisa membuatmu ikut sedih atau marah, terutama jika pelakunya kamu kenal. Kamu juga boleh bercerita ke konselor, tanpa menyebut nama temanmu.

> Di lingkungan kampus
> Perguruan tinggi wajib memiliki Satuan Tugas Pencegahan dan Penanganan Kekerasan. Temanmu bisa melapor ke Satgas kampus, dan pendamping Haven dapat membantu menyiapkan langkahnya.',
'Kementerian Pemberdayaan Perempuan dan Perlindungan Anak | https://www.kemenpppa.go.id/
Komnas Perempuan | https://komnasperempuan.go.id/
Permendikbudristek No. 55 Tahun 2024 tentang Pencegahan dan Penanganan Kekerasan di Lingkungan Perguruan Tinggi, JDIH BPK | https://peraturan.bpk.go.id/',
 'img/holding-hands.jpg', 'Dua orang saling menggenggam tangan', 4, 'terbit', '2026-11-03 09:00:00', '2026-11-03 09:00:00', '2026-11-03 09:00:00'),

(1, 1, 'Memahami consent: setuju yang sesungguhnya', 'memahami-consent',
 'Diam bukan berarti iya. Persetujuan harus sadar, sukarela, dan boleh ditarik kapan pun.',
'Consent, atau persetujuan, adalah kesepakatan yang jelas dan sukarela untuk melakukan aktivitas seksual tertentu. Tanpa persetujuan, aktivitas itu adalah kekerasan.

## Ciri persetujuan yang sah
- **Sukarela.** Diberikan tanpa paksaan, ancaman, atau tekanan.
- **Sadar.** Orang itu mampu memahami dan memutuskan, tidak dalam keadaan mabuk berat, tertidur, atau pingsan.
- **Spesifik.** Setuju pada satu hal tidak berarti setuju pada hal lain.
- **Bisa ditarik.** Seseorang boleh berubah pikiran kapan saja, bahkan di tengah-tengah.
- **Jelas.** Bila ragu, tanyakan. Ketiadaan penolakan tidak sama dengan persetujuan.

## Yang bukan persetujuan
- Diam atau tidak melawan. Banyak orang membeku karena takut.
- Pernah setuju sebelumnya, atau sedang berpacaran atau menikah.
- "Iya" yang diucapkan karena takut kehilangan pekerjaan, nilai, atau hubungan.
- Persetujuan dari anak. Anak tidak dapat memberi persetujuan seksual.

## Relasi kuasa
Persetujuan sulit benar-benar bebas bila ada ketimpangan kuasa, misalnya dosen dan mahasiswa, atasan dan bawahan, atau orang dewasa dan remaja. Orang yang lebih berkuasa bertanggung jawab untuk tidak memanfaatkan posisinya.

> Cara sederhana
> Tanyakan, dengarkan jawabannya, dan hormati. "Tidak", "nanti dulu", atau keraguan berarti berhenti.',
'UU RI No. 12 Tahun 2022 tentang Tindak Pidana Kekerasan Seksual, JDIH BPK | https://peraturan.bpk.go.id/
Komnas Perempuan | https://komnasperempuan.go.id/',
 'img/support-circle.jpg', 'Kelompok dukungan duduk melingkar', 4, 'terbit', '2026-10-30 09:00:00', '2026-10-30 09:00:00', '2026-10-30 09:00:00'),

(5, 1, 'Kekerasan seksual berbasis elektronik dan cara melindungi diri', 'kekerasan-berbasis-elektronik',
 'Ancaman menyebar foto, pesan cabul, dan penguntitan daring. Begini cara mengamankan bukti dan akun.',
'UU TPKS memasukkan kekerasan seksual berbasis elektronik sebagai tindak pidana. Artinya ancaman menyebar foto, pemerasan dengan konten intim, dan penguntitan lewat ponsel bisa dilaporkan seperti kekerasan lain.

## Bentuk yang sering terjadi
- Menyebarkan atau mengancam menyebarkan foto atau video intim tanpa izin.
- Memeras dengan konten intim, misalnya meminta uang atau konten lain agar tidak disebar.
- Merekam atau memotret bagian tubuh seseorang secara diam-diam.
- Mengirim pesan, gambar, atau video seksual yang tidak diminta.
- Menguntit lewat lokasi, akun, atau perangkat seseorang.
- Membuat konten seksual palsu yang memakai wajah seseorang.

## Jika kamu diancam
- **Jangan menuruti permintaan pelaku.** Memberi uang atau konten lain biasanya tidak menghentikan ancaman.
- **Simpan bukti.** Tangkapan layar percakapan, nama akun, tautan, dan tanggal. Simpan di tempat yang aman.
- **Laporkan ke platform.** Sebagian besar media sosial punya formulir khusus untuk konten intim tanpa persetujuan.
- **Cari dukungan.** Ceritakan ke orang tepercaya atau [buat laporan di Haven](/lapor).

## Amankan akun dan perangkat
- Ganti kata sandi dan aktifkan verifikasi dua langkah.
- Periksa perangkat yang masuk ke akunmu dan keluarkan yang tidak dikenal.
- Matikan berbagi lokasi yang tidak perlu.
- Periksa aplikasi asing di ponsel yang mungkin dipasang orang lain.

> Mengirim foto bukan izin untuk menyebarkannya
> Yang melanggar hukum adalah orang yang menyebarkan atau mengancam menyebarkan, bukan kamu yang dulu mengirimnya.',
'UU RI No. 12 Tahun 2022 tentang Tindak Pidana Kekerasan Seksual, JDIH BPK | https://peraturan.bpk.go.id/
Komnas Perempuan, kekerasan berbasis gender online | https://komnasperempuan.go.id/
Kementerian Pemberdayaan Perempuan dan Perlindungan Anak | https://www.kemenpppa.go.id/',
 'img/phone.jpg', 'Perempuan berhijab memegang ponsel', 6, 'terbit', '2026-10-27 09:00:00', '2026-10-27 09:00:00', '2026-10-27 09:00:00'),

(1, 1, 'Mengenali grooming sejak dini', 'mengenali-grooming',
 'Perhatian berlebih, hadiah, dan permintaan merahasiakan. Tanda-tanda yang sering terlewat.',
'Grooming adalah proses ketika pelaku membangun kedekatan dan kepercayaan secara bertahap agar bisa memanipulasi dan melakukan kekerasan seksual. Grooming sering terjadi pada anak dan remaja, tetapi juga bisa terjadi pada orang dewasa, terutama dalam relasi yang timpang.

## Tanda-tanda yang perlu diwaspadai
- Perhatian, pujian, atau hadiah yang berlebihan dan terasa "terlalu istimewa".
- Mengajak bicara di kanal pribadi dan meminta percakapan dirahasiakan.
- Perlahan memisahkan korban dari teman atau keluarganya.
- Menguji batas sedikit demi sedikit, misalnya lelucon seksual, sentuhan "tidak sengaja", atau meminta foto.
- Membuat korban merasa berutang budi atau takut kehilangan sesuatu.

## Di lingkungan pendidikan dan kerja
Grooming juga bisa dilakukan orang yang punya kuasa, seperti pengajar, pembimbing, atau atasan. Polanya mirip: perhatian khusus, janji keuntungan, lalu permintaan yang melewati batas dengan ancaman tersirat.

## Apa yang bisa dilakukan
- Percayai rasa tidak nyamanmu. Kamu tidak perlu menunggu "bukti kuat" untuk menjaga jarak.
- Ceritakan kepada orang yang kamu percaya. Rahasia yang diminta pelaku adalah alat untuk mengontrol.
- Simpan pesan dan hadiah sebagai bukti.
- Hubungi Satgas kampus, UPTD PPA, atau [buat laporan di Haven](/lapor).

> Untuk orang tua dan pengasuh
> Ajak anak berbicara tentang tubuh dan batasnya, dan tegaskan bahwa tidak ada orang dewasa yang boleh meminta rahasia tentang sentuhan atau foto.',
'UU RI No. 12 Tahun 2022 tentang Tindak Pidana Kekerasan Seksual, JDIH BPK | https://peraturan.bpk.go.id/
Kementerian Pemberdayaan Perempuan dan Perlindungan Anak | https://www.kemenpppa.go.id/
Komnas Perempuan | https://komnasperempuan.go.id/',
 'img/laptop.jpg', 'Tangan mengetik di laptop', 5, 'terbit', '2026-10-24 09:00:00', '2026-10-24 09:00:00', '2026-10-24 09:00:00')
ON DUPLICATE KEY UPDATE
 category_id = VALUES(category_id), author_id = VALUES(author_id), title = VALUES(title), excerpt = VALUES(excerpt),
 body = VALUES(body), sources = VALUES(sources), cover_path = VALUES(cover_path), cover_alt = VALUES(cover_alt),
 read_minutes = VALUES(read_minutes), status = VALUES(status), published_at = VALUES(published_at), updated_at = VALUES(updated_at);

-- Five new SDG 5 articles (Requirement 3). Original Bahasa Indonesia text written for this project.
-- Each article names the issuing body, the number or title of the law, and the year in its own body text.
INSERT INTO articles (category_id, author_id, title, slug, excerpt, body, sources, cover_path, cover_alt, read_minutes, status, published_at, created_at, updated_at) VALUES
(6, 1, 'Mengenali diskriminasi terhadap perempuan di kampus dan tempat kerja', 'diskriminasi-perempuan-kampus-dan-kerja',
 'Bukan hanya soal upah. Diskriminasi lebih sering muncul lewat syarat lowongan, pembagian tugas, dan lelucon yang dianggap biasa.',
'Diskriminasi tidak selalu terdengar kasar. Di kampus dan di tempat kerja, bentuknya sering halus: nama yang dilewati saat pembagian tugas penting, pertanyaan tentang rencana menikah dalam wawancara, atau lelucon yang membuat satu orang di ruangan terdiam. Karena halus, kejadian seperti itu mudah dianggap kebetulan. Padahal pola yang berulang punya akibat nyata pada nilai, karier, dan rasa aman seseorang.

Konvensi mengenai Penghapusan Segala Bentuk Diskriminasi terhadap Wanita yang disahkan Majelis Umum Perserikatan Bangsa-Bangsa pada 1979 merumuskan diskriminasi sebagai pembedaan, pengucilan, atau pembatasan atas dasar jenis kelamin yang berakibat mengurangi pengakuan atau pelaksanaan hak seseorang. Indonesia meratifikasi konvensi itu melalui Undang-Undang Republik Indonesia Nomor 7 Tahun 1984. Artinya, sejak tahun itu negara terikat untuk menghapus praktik semacam ini, termasuk di ruang pendidikan dan dunia kerja.

## Dua wajah diskriminasi
**Langsung**, ketika seseorang diperlakukan berbeda secara terbuka karena jenis kelaminnya. Contohnya lowongan yang mensyaratkan pelamar laki-laki untuk pekerjaan yang tidak menuntut syarat khusus apa pun, atau pengumuman beasiswa yang menutup pintu bagi mahasiswa yang sudah menikah.

**Tidak langsung**, ketika aturan terdengar netral tetapi akibatnya berat sebelah. Contohnya rapat penentu yang selalu dijadwalkan pukul sembilan malam, syarat tinggi badan yang tidak berhubungan dengan tugas, atau penilaian kinerja yang memberi nilai rendah kepada pekerja yang mengambil cuti melahirkan.

## Yang sering terjadi di kampus
- Proyek lapangan atau topik penelitian dialihkan dari mahasiswa perempuan dengan alasan terlalu berat.
- Komentar tentang tubuh, pakaian, atau status pernikahan di ruang kelas dan ruang bimbingan.
- Nilai dan jadwal bimbingan dipakai sebagai alat tekanan oleh pihak yang lebih berkuasa.
- Laporan kekerasan diminta diselesaikan secara kekeluargaan agar nama kampus aman.

Kementerian Pendidikan, Kebudayaan, Riset, dan Teknologi menerbitkan Peraturan Menteri Nomor 30 Tahun 2021 tentang Pencegahan dan Penanganan Kekerasan Seksual di Lingkungan Perguruan Tinggi. Peraturan itu mewajibkan perguruan tinggi membentuk satuan tugas dan menyediakan jalur penanganan, sehingga penyelesaian kekeluargaan yang menekan korban bukan prosedur yang sah.

## Yang sering terjadi di tempat kerja
- Pertanyaan tentang haid, kehamilan, atau rencana punya anak dalam wawancara kerja.
- Upah berbeda untuk pekerjaan dengan nilai yang sama.
- Promosi yang tertutup karena jabatan dianggap menuntut orang yang bisa diajak lembur kapan saja.
- Tugas administratif seperti mencatat rapat dan menyiapkan konsumsi yang selalu jatuh ke pekerja perempuan.

Undang-Undang Republik Indonesia Nomor 13 Tahun 2003 tentang Ketenagakerjaan menyebut pada Pasal 5 bahwa setiap tenaga kerja berhak memperoleh kesempatan yang sama tanpa diskriminasi, dan pada Pasal 6 bahwa setiap pekerja berhak memperoleh perlakuan yang sama dari pengusaha. Indonesia juga sudah meratifikasi Konvensi Organisasi Buruh Internasional Nomor 111 mengenai diskriminasi dalam pekerjaan dan jabatan melalui Undang-Undang Republik Indonesia Nomor 21 Tahun 1999.

## Mencatat sebelum melapor
Satu peristiwa sulit dibuktikan, pola lebih mudah. Simpan catatan sederhana: tanggal, tempat, siapa yang hadir, apa yang dikatakan, dan akibatnya pada studi atau pekerjaanmu. Simpan juga tangkapan layar pesan dan surat tugas. Catatan ini berguna untuk jalur internal maupun jalur hukum, dan tetap milikmu bila akhirnya kamu memilih tidak melapor.

## Ke mana membawa persoalan ini
- Di kampus: satuan tugas pencegahan dan penanganan kekerasan, atau dosen yang kamu percaya.
- Di tempat kerja: serikat pekerja, bagian sumber daya manusia, atau dinas yang menangani ketenagakerjaan di daerahmu.
- Di luar keduanya: lembaga bantuan hukum, Unit Pelaksana Teknis Daerah Perlindungan Perempuan dan Anak, atau pendamping Haven lewat [formulir laporan](/lapor).

> Diskriminasi bukan soal niat
> Kamu tidak perlu membuktikan bahwa pelaku berniat jahat. Yang dinilai adalah akibatnya: apakah hakmu atas pendidikan dan pekerjaan menjadi berkurang.

Bila diskriminasi berkembang menjadi pelecehan atau kekerasan seksual, Undang-Undang Republik Indonesia Nomor 12 Tahun 2022 tentang Tindak Pidana Kekerasan Seksual memberi dasar untuk menuntut penanganan, pelindungan, dan pemulihan. Kamu bisa melihat [direktori layanan](/layanan) untuk mencari pendampingan terdekat.',
'UU RI No. 7 Tahun 1984 tentang Pengesahan Konvensi mengenai Penghapusan Segala Bentuk Diskriminasi terhadap Wanita, JDIH BPK | https://peraturan.bpk.go.id/
UU RI No. 13 Tahun 2003 tentang Ketenagakerjaan, Pasal 5 dan Pasal 6, JDIH BPK | https://peraturan.bpk.go.id/
UU RI No. 21 Tahun 1999 tentang Pengesahan Konvensi ILO No. 111 mengenai Diskriminasi dalam Pekerjaan dan Jabatan, JDIH BPK | https://peraturan.bpk.go.id/
Permendikbudristek No. 30 Tahun 2021 tentang Pencegahan dan Penanganan Kekerasan Seksual di Lingkungan Perguruan Tinggi, JDIH Kemendikbud | https://jdih.kemdikbud.go.id/
Komnas Perempuan | https://komnasperempuan.go.id/',
 'img/laptop.jpg', 'Tangan mengetik di laptop di atas meja kerja', 7, 'terbit', '2026-11-18 09:00:00', '2026-11-18 09:00:00', '2026-11-18 09:00:00'),

(7, 1, 'Perempuan memimpin: dari ruang kelas sampai ruang rapat', 'kepemimpinan-dan-partisipasi-perempuan',
 'Kuota 30 persen bukan belas kasihan. Ini dasar hukumnya, hambatan yang jarang disebut, dan cara organisasi membuka jalan.',
'Ketika perempuan ikut memutuskan, keputusan biasanya menyentuh hal yang sebelumnya luput: jam layanan kesehatan, keamanan jalan pulang, ruang menyusui, sampai cara sebuah organisasi menangani laporan pelecehan. Jadi partisipasi bukan sekadar keadilan bagi satu orang yang dipromosikan, melainkan soal mutu keputusan yang dipakai semua orang.

## Kesetaraan yang sudah dijanjikan
Perserikatan Bangsa-Bangsa menetapkan agenda Tujuan Pembangunan Berkelanjutan pada 2015. Tujuan kelima, kesetaraan gender, memuat target memastikan perempuan ikut penuh dan efektif serta mendapat kesempatan setara untuk memimpin pada semua tingkat pengambilan keputusan. Indonesia menerjemahkan agenda itu lewat Peraturan Presiden Republik Indonesia Nomor 59 Tahun 2017 tentang Pelaksanaan Pencapaian Tujuan Pembangunan Berkelanjutan.

Jauh sebelum itu, Instruksi Presiden Republik Indonesia Nomor 9 Tahun 2000 tentang Pengarusutamaan Gender dalam Pembangunan Nasional sudah meminta setiap kementerian dan pemerintah daerah memasukkan perspektif gender ke dalam perencanaan dan penganggaran.

## Angka 30 persen dan dari mana asalnya
Dua undang-undang menyebut keterwakilan perempuan secara eksplisit:

- Undang-Undang Republik Indonesia Nomor 2 Tahun 2008 tentang Partai Politik mensyaratkan keterwakilan perempuan paling sedikit 30 persen dalam pendirian dan kepengurusan partai politik tingkat pusat.
- Undang-Undang Republik Indonesia Nomor 7 Tahun 2017 tentang Pemilihan Umum mewajibkan daftar bakal calon anggota legislatif memuat paling sedikit 30 persen perempuan.

Angka itu bukan hadiah. Ia dipakai sebagai ambang minimum agar satu kelompok tidak menjadi suara tunggal yang mudah diabaikan di ruang rapat.

## Hambatan yang jarang dibahas dalam rapat
- **Beban ganda.** Pekerjaan rumah dan pengasuhan yang tidak dibagi membuat jam kerja tambahan menjadi mahal.
- **Jadwal.** Rapat penentu pada malam hari atau akhir pekan menutup pintu tanpa perlu melarang siapa pun.
- **Stereotip penilaian.** Ketegasan pada laki-laki dibaca sebagai kepemimpinan, pada perempuan dibaca sebagai sulit diatur.
- **Kriteria yang tidak tertulis.** Promosi lewat kedekatan pribadi menguntungkan orang yang sudah berada di lingkaran dalam.
- **Pelecehan.** Ruang yang tidak aman membuat orang memilih mundur daripada bertahan.

## Yang bisa dilakukan organisasi
1. Tulis kriteria promosi dan pembagian tugas secara terbuka, lalu benar-benar pakai kriteria itu.
2. Jadwalkan rapat penting pada jam kerja, dan sediakan catatan rapat bagi yang tidak bisa hadir.
3. Putar peran notulis dan pembawa acara, jangan jadikan tugas tetap satu jenis kelamin.
4. Sediakan jalur pelaporan pelecehan yang jelas dengan pelaksana yang terlatih.
5. Pasangkan calon pemimpin dengan mentor, dan beri tugas yang membuat mereka terlihat, bukan hanya tugas yang membuat mereka sibuk.

## Yang bisa dilakukan siapa saja
Di organisasi mahasiswa, serikat pekerja, atau musyawarah desa, hal kecil mengubah banyak hal: mengembalikan gagasan kepada orang yang pertama menyampaikannya ketika gagasan itu diulang orang lain, mencalonkan nama yang biasa terlewat, dan memastikan daftar pembicara tidak berisi satu jenis kelamin saja.

## Kepemimpinan juga soal keamanan
Organisasi yang ingin perempuan bertahan di jalur kepemimpinan perlu memastikan ruangnya aman. Undang-Undang Republik Indonesia Nomor 12 Tahun 2022 tentang Tindak Pidana Kekerasan Seksual mengatur pelecehan seksual nonfisik dan fisik sebagai tindak pidana, dan memberi korban hak atas penanganan, pelindungan, serta pemulihan. Aturan internal yang jelas membuat hak itu bisa dipakai tanpa harus menunggu proses panjang.

> Representasi tidak berhenti di jumlah
> Satu kursi tanpa wewenang bukan kepemimpinan. Perhatikan apakah orang yang dipilih benar-benar memegang anggaran, agenda, dan keputusan.

Jika proses pemilihan atau promosi di tempatmu diwarnai ancaman atau pelecehan, kamu bisa berbicara dengan pendamping lewat [formulir laporan](/lapor) atau mencari lembaga pendamping di [direktori layanan](/layanan).',
'Perserikatan Bangsa-Bangsa, Tujuan Pembangunan Berkelanjutan, Tujuan 5, 2015 | https://sdgs.un.org/goals/goal5
Perpres RI No. 59 Tahun 2017 tentang Pelaksanaan Pencapaian Tujuan Pembangunan Berkelanjutan, JDIH BPK | https://peraturan.bpk.go.id/
UU RI No. 2 Tahun 2008 tentang Partai Politik, JDIH BPK | https://peraturan.bpk.go.id/
UU RI No. 7 Tahun 2017 tentang Pemilihan Umum, JDIH BPK | https://peraturan.bpk.go.id/
Inpres RI No. 9 Tahun 2000 tentang Pengarusutamaan Gender dalam Pembangunan Nasional, JDIH BPK | https://peraturan.bpk.go.id/',
 'img/support-circle.jpg', 'Sekelompok orang duduk melingkar dan berdiskusi', 7, 'terbit', '2026-11-17 09:00:00', '2026-11-17 09:00:00', '2026-11-17 09:00:00'),

(1, 1, 'Persetujuan dalam praktik: bertanya, mendengar, berhenti', 'persetujuan-dalam-praktik',
 'Persetujuan bukan formalitas sekali ucap. Ini latihan bertanya dan membaca jawaban dalam relasi sehari-hari, termasuk secara daring.',
'Banyak orang sudah tahu rumusnya: tidak berarti tidak. Yang lebih sulit adalah mempraktikkannya ketika suasana sedang canggung, ketika sudah lama berpacaran, atau ketika percakapan terjadi lewat pesan singkat. Artikel ini membahas persetujuan sebagai keterampilan sehari-hari, bukan sebagai pasal yang dihafal.

## Bertanya tidak merusak suasana
Pertanyaan sederhana sudah cukup: boleh, kamu nyaman, atau mau berhenti dulu. Pertanyaan semacam itu memberi dua hal sekaligus, yaitu kejelasan bagi yang bertanya dan ruang bagi yang menjawab. Yang merusak suasana biasanya bukan pertanyaannya, melainkan kemarahan ketika jawabannya tidak.

## Membaca jawaban yang tidak berbunyi
Tubuh sering menjawab lebih dulu daripada mulut. Menarik tangan, memalingkan badan, tubuh yang mengeras, mata yang menghindar, atau tiba-tiba terdiam adalah jawaban. Sebagian orang membeku ketika merasa terancam, dan kondisi itu bukan tanda setuju. Bila kamu tidak yakin, berhenti dan tanyakan. Ketiadaan penolakan bukan persetujuan.

## Persetujuan bisa ditarik
Seseorang boleh berubah pikiran, termasuk setelah sesuatu dimulai. Setuju kemarin tidak berarti setuju hari ini, dan setuju pada satu hal tidak berarti setuju pada hal lain. Menarik kembali persetujuan tidak perlu disertai alasan.

## Ketika kuasa ikut bicara
Dosen dan mahasiswa, atasan dan bawahan, pelatih dan atlet, pembimbing dan peserta magang: dalam relasi seperti ini, kata iya bisa lahir dari rasa takut kehilangan nilai, pekerjaan, atau kesempatan. Pihak yang lebih berkuasa bertanggung jawab untuk tidak membuka ruang itu sejak awal.

## Dalam pacaran dan pernikahan
Hubungan dekat tidak menghapus syarat persetujuan. Undang-Undang Republik Indonesia Nomor 23 Tahun 2004 tentang Penghapusan Kekerasan dalam Rumah Tangga melarang kekerasan seksual dalam lingkup rumah tangga, termasuk pemaksaan hubungan seksual terhadap orang yang berada dalam lingkup rumah tangga itu. Rasa sayang, status pernikahan, dan lamanya hubungan bukan alasan yang sah.

## Anak tidak dapat memberi persetujuan
Undang-Undang Republik Indonesia Nomor 35 Tahun 2014 tentang Perubahan atas Undang-Undang Nomor 23 Tahun 2002 tentang Perlindungan Anak menegaskan hak anak untuk dilindungi dari kekerasan dan eksploitasi seksual. Undang-Undang Republik Indonesia Nomor 16 Tahun 2019 tentang Perubahan atas Undang-Undang Nomor 1 Tahun 1974 tentang Perkawinan juga menaikkan batas usia minimal perkawinan menjadi 19 tahun, baik bagi perempuan maupun laki-laki. Aktivitas seksual dengan anak adalah kekerasan, apa pun yang anak itu katakan.

## Di ruang digital
- Mengirim foto intim kepada seseorang bukan izin untuk menyebarkannya kepada orang lain.
- Permintaan foto yang disertai rengekan, ancaman, atau janji imbalan bukan persetujuan.
- Merekam pertemuan intim membutuhkan persetujuan terpisah, dan persetujuan itu juga bisa ditarik.

Undang-Undang Republik Indonesia Nomor 12 Tahun 2022 tentang Tindak Pidana Kekerasan Seksual memasukkan kekerasan seksual berbasis elektronik sebagai tindak pidana, termasuk perekaman dan penyebaran konten bermuatan seksual tanpa persetujuan.

## Kalau kamu pernah melewati batas
Mengakui lebih berguna daripada membela diri. Berhenti, minta maaf tanpa menuntut dimaafkan, jangan mengulangi, dan jangan menghubungi orang itu bila ia meminta jarak. Membicarakannya dengan konselor membantu memastikan hal yang sama tidak terjadi lagi.

> Latihan paling pendek
> Tanya, dengarkan, hormati jawabannya. Bila ragu, anggap jawabannya tidak.

Kalau kamu mengalami sesuatu yang terasa melewati batas, kamu boleh bercerita lebih dulu tanpa harus melapor ke polisi. [Buat laporan di Haven](/lapor) atau lihat [direktori layanan](/layanan).',
'UU RI No. 12 Tahun 2022 tentang Tindak Pidana Kekerasan Seksual, JDIH BPK | https://peraturan.bpk.go.id/
UU RI No. 23 Tahun 2004 tentang Penghapusan Kekerasan dalam Rumah Tangga, JDIH BPK | https://peraturan.bpk.go.id/
UU RI No. 35 Tahun 2014 tentang Perubahan atas UU No. 23 Tahun 2002 tentang Perlindungan Anak, JDIH BPK | https://peraturan.bpk.go.id/
UU RI No. 16 Tahun 2019 tentang Perubahan atas UU No. 1 Tahun 1974 tentang Perkawinan, JDIH BPK | https://peraturan.bpk.go.id/
Komnas Perempuan | https://komnasperempuan.go.id/',
 'img/holding-hands.jpg', 'Dua orang saling menggenggam tangan', 6, 'terbit', '2026-11-16 09:00:00', '2026-11-16 09:00:00', '2026-11-16 09:00:00'),

(8, 1, 'Laki-laki sebagai sekutu: dari diam menjadi bertindak', 'laki-laki-sebagai-sekutu',
 'Menjadi sekutu bukan soal menjadi pahlawan. Ini soal menolak lelucon, menegur teman, dan percaya ketika seseorang bercerita.',
'Kekerasan seksual sering dibicarakan sebagai urusan perempuan. Padahal sebagian besar ruang tempat kekerasan itu dibiarkan justru diisi laki-laki: grup percakapan, kantin kantor, lapangan futsal, ruang rapat. Di ruang-ruang itulah seorang sekutu paling berguna.

Menjadi sekutu bukan menjadi pahlawan yang menyelamatkan. Ini pekerjaan kecil yang diulang terus: menolak lelucon, menegur teman, percaya ketika seseorang bercerita, dan tidak mengambil alih keputusan orang lain.

## Mulai dari bahasa sehari-hari
Lelucon tentang tubuh, julukan merendahkan, dan cerita penaklukan terdengar sepele, tetapi semuanya menyampaikan satu pesan kepada orang di sekitarnya: di sini perbuatan seperti itu aman. Menolak ikut tertawa saja sudah mengubah suhu ruangan. Satu kalimat pendek biasanya cukup, misalnya tidak lucu, atau ganti topik.

## Empat cara menolong tanpa memperburuk
Ketika kamu melihat seseorang diganggu, pilihanmu lebih dari satu:

1. **Alihkan.** Sapa korban dengan alasan biasa, tanyakan arah, atau minta tolong sesuatu, sehingga situasi terputus.
2. **Libatkan orang lain.** Panggil petugas keamanan, pengemudi, panitia, atau orang yang lebih berwenang.
3. **Catat.** Bila aman, tulis waktu, tempat, dan ciri pelaku. Serahkan catatan itu kepada korban, jangan diunggah ke media sosial.
4. **Tegur langsung.** Pilihan ini hanya dipakai bila kamu yakin tidak menambah bahaya bagi korban.

Setelah situasi aman, tanyakan kepada korban apa yang ia butuhkan. Jangan menelepon polisi atau keluarganya tanpa persetujuannya.

## Ketika teman bercerita
Tiga jawaban paling penting: aku percaya kamu, ini bukan salahmu, dan apa yang kamu butuhkan sekarang. Hindari pertanyaan yang terdengar seperti pemeriksaan, misalnya mengapa tidak melawan atau mengapa baru cerita sekarang. Jangan menceritakan kembali kisah itu kepada orang lain tanpa izin, walaupun maksudmu membantu.

## Ketika pelakunya temanmu
Ini bagian tersulit, karena kesetiaan kepada teman sering menang atas kejujuran. Yang bisa kamu lakukan: hentikan perbuatannya saat itu juga, katakan dengan jelas bahwa itu salah, jangan membantu menutupi, dan jangan menekan korban supaya berdamai. Mendukung proses yang adil bukan pengkhianatan terhadap pertemanan.

## Di rumah dan di tempat kerja
Pembagian kerja rumah tangga, siapa yang mengantar anak, siapa yang selalu mencatat rapat, siapa yang namanya disebut saat promosi: semuanya bagian dari persoalan yang sama. Sekutu memeriksa daftar pembicara yang hanya berisi laki-laki, menyebut nama rekan yang gagasannya diambil orang lain, dan mendukung aturan pencegahan kekerasan di kantor atau kampus.

## Dasar hukumnya menyebut peran kita
Undang-Undang Republik Indonesia Nomor 12 Tahun 2022 tentang Tindak Pidana Kekerasan Seksual tidak berhenti pada pemidanaan. Undang-undang itu juga mengatur pencegahan serta peran keluarga, masyarakat, dan lembaga dalam mencegah kekerasan seksual. Di perguruan tinggi, Peraturan Menteri Pendidikan, Kebudayaan, Riset, dan Teknologi Nomor 30 Tahun 2021 tentang Pencegahan dan Penanganan Kekerasan Seksual di Lingkungan Perguruan Tinggi mewajibkan kampus membentuk satuan tugas, dan mahasiswa laki-laki bisa ikut terlibat di dalamnya.

## Jika kamu pernah menjadi pelaku
Berhenti, dengarkan, dan jangan menuntut pengampunan. Jangan menghubungi orang yang kamu lukai bila ia meminta jarak. Bicarakan dengan konselor agar perbuatan itu tidak terulang. Pertanggungjawaban adalah bagian dari menjadi sekutu, bukan kebalikannya.

> Ukurannya bukan niat
> Sekutu dinilai dari apa yang berubah di sekitarnya, bukan dari seberapa sering ia menyebut dirinya mendukung kesetaraan.

Laki-laki juga bisa menjadi korban kekerasan seksual, dan berhak atas pendampingan yang sama. [Buat laporan di Haven](/lapor) atau lihat [direktori layanan](/layanan).',
'UU RI No. 12 Tahun 2022 tentang Tindak Pidana Kekerasan Seksual, ketentuan pencegahan dan peran serta masyarakat, JDIH BPK | https://peraturan.bpk.go.id/
Permendikbudristek No. 30 Tahun 2021 tentang Pencegahan dan Penanganan Kekerasan Seksual di Lingkungan Perguruan Tinggi, JDIH Kemendikbud | https://jdih.kemdikbud.go.id/
Kementerian Pemberdayaan Perempuan dan Perlindungan Anak | https://www.kemenpppa.go.id/
Komnas Perempuan | https://komnasperempuan.go.id/',
 'img/reaching-hands.jpg', 'Dua tangan saling meraih di latar abu-abu', 6, 'terbit', '2026-11-14 09:00:00', '2026-11-14 09:00:00', '2026-11-14 09:00:00'),

(9, 1, 'Hak pekerja perempuan yang sering tidak diberitahukan', 'hak-perempuan-di-tempat-kerja',
 'Cuti haid, cuti melahirkan, waktu menyusui, dan larangan pemecatan karena hamil. Dasar hukumnya ada, dan ini cara menagihnya.',
'Banyak hak pekerja perempuan tidak disembunyikan, hanya tidak pernah diberitahukan. Bagian di bawah merangkum hak yang paling sering dipakai beserta dasar hukumnya, supaya kamu bisa menyebut pasalnya ketika perlu.

Rujukan utamanya adalah Undang-Undang Republik Indonesia Nomor 13 Tahun 2003 tentang Ketenagakerjaan. Sebagian ketentuannya diubah oleh Undang-Undang Republik Indonesia Nomor 6 Tahun 2023 tentang Penetapan Peraturan Pemerintah Pengganti Undang-Undang Nomor 2 Tahun 2022 tentang Cipta Kerja menjadi Undang-Undang, jadi sebaiknya kamu membaca naskah terbaru pada laman resmi yang tercantum di daftar sumber.

## Kesempatan dan perlakuan yang sama
Pasal 5 undang-undang itu menyatakan setiap tenaga kerja berhak memperoleh kesempatan yang sama tanpa diskriminasi, dan Pasal 6 menyatakan setiap pekerja berhak memperoleh perlakuan yang sama dari pengusaha. Untuk soal upah, Indonesia sudah meratifikasi Konvensi Organisasi Buruh Internasional Nomor 100 mengenai pengupahan yang sama bagi pekerjaan yang sama nilainya melalui Undang-Undang Republik Indonesia Nomor 80 Tahun 1957.

## Cuti haid
Pasal 81 mengatur bahwa pekerja perempuan yang merasakan sakit pada masa haid dan memberitahukannya kepada pengusaha tidak wajib bekerja pada hari pertama dan kedua masa haid. Pemberitahuan sudah cukup, dan pelaksanaannya diatur lebih lanjut dalam perjanjian kerja atau peraturan perusahaan.

## Cuti melahirkan dan keguguran
Pasal 82 memberi hak istirahat satu setengah bulan sebelum melahirkan dan satu setengah bulan sesudah melahirkan, menurut perhitungan dokter kandungan atau bidan. Pekerja yang mengalami keguguran berhak atas istirahat satu setengah bulan atau sesuai surat keterangan dokter kandungan atau bidan.

Pada 2024 terbit Undang-Undang Republik Indonesia Nomor 4 Tahun 2024 tentang Kesejahteraan Ibu dan Anak pada Fase Seribu Hari Pertama Kehidupan. Undang-undang itu mengatur hak cuti melahirkan paling singkat tiga bulan, yang dapat diperpanjang hingga tiga bulan berikutnya bila ada kondisi khusus yang dibuktikan dengan surat keterangan dokter, serta hak suami untuk mendampingi istri yang melahirkan.

## Kesempatan menyusui
Pasal 83 mewajibkan pengusaha memberi kesempatan yang sepatutnya kepada pekerja perempuan untuk menyusui anaknya bila hal itu harus dilakukan selama waktu kerja. Tempat yang layak dan jadwal yang memungkinkan adalah bagian dari kewajiban tersebut.

## Upah selama istirahat
Pasal 84 menegaskan bahwa pekerja yang menggunakan hak istirahat atau cuti yang diatur undang-undang tetap berhak atas upah penuh.

## Bekerja pada malam hari
Pasal 76 mengatur perlindungan bagi pekerja perempuan yang bekerja malam. Pengusaha wajib menyediakan makanan dan minuman bergizi, menjaga kesusilaan dan keamanan selama di tempat kerja, serta menyediakan angkutan antar jemput bagi pekerja perempuan yang berangkat dan pulang bekerja antara pukul 23.00 dan pukul 05.00. Pekerja perempuan yang hamil dan menurut keterangan dokter berbahaya bagi kesehatan dirinya atau kandungannya tidak boleh dipekerjakan antara pukul 23.00 dan pukul 07.00.

## Tidak boleh dipecat karena menikah atau hamil
Pasal 153 melarang pemutusan hubungan kerja dengan alasan pekerja menikah, hamil, melahirkan, gugur kandungan, atau menyusui. Pemutusan hubungan kerja yang tetap dilakukan dengan alasan itu batal karena hukum, dan pengusaha wajib mempekerjakan kembali pekerja yang bersangkutan.

## Bebas dari pelecehan
Pelecehan di tempat kerja bukan risiko pekerjaan. Undang-Undang Republik Indonesia Nomor 12 Tahun 2022 tentang Tindak Pidana Kekerasan Seksual mengatur pelecehan seksual nonfisik maupun fisik sebagai tindak pidana, dan memberi korban hak atas penanganan, pelindungan, serta pemulihan. Perbuatan itu tetap bisa dilaporkan meskipun pelakunya atasan.

## Langkah praktis bila hakmu tidak dipenuhi
1. Kumpulkan dokumen: perjanjian kerja, peraturan perusahaan, slip upah, surat keterangan dokter, dan pesan yang berkaitan.
2. Sampaikan permintaanmu secara tertulis, misalnya lewat surel, supaya ada jejaknya.
3. Minta pendampingan serikat pekerja bila di tempatmu ada.
4. Ajukan pengaduan ke dinas yang menangani ketenagakerjaan di daerahmu, yang menyediakan mediator hubungan industrial.
5. Untuk kekerasan atau pelecehan, hubungi Unit Pelaksana Teknis Daerah Perlindungan Perempuan dan Anak, telepon 129, atau [buat laporan di Haven](/lapor).

> Mintalah secara tertulis
> Permintaan lisan mudah dilupakan. Satu surel singkat yang menyebut pasal dan tanggal jauh lebih kuat ketika persoalan berlanjut ke mediasi.',
'UU RI No. 13 Tahun 2003 tentang Ketenagakerjaan, Pasal 5, 6, 76, 81, 82, 83, 84, dan 153, JDIH BPK | https://peraturan.bpk.go.id/
UU RI No. 6 Tahun 2023 tentang Penetapan Perppu No. 2 Tahun 2022 tentang Cipta Kerja menjadi Undang-Undang, JDIH BPK | https://peraturan.bpk.go.id/
UU RI No. 80 Tahun 1957 tentang Pengesahan Konvensi ILO No. 100 mengenai Pengupahan yang Sama bagi Pekerjaan yang Sama Nilainya, JDIH BPK | https://peraturan.bpk.go.id/
UU RI No. 4 Tahun 2024 tentang Kesejahteraan Ibu dan Anak pada Fase Seribu Hari Pertama Kehidupan, JDIH BPK | https://peraturan.bpk.go.id/
UU RI No. 12 Tahun 2022 tentang Tindak Pidana Kekerasan Seksual, JDIH BPK | https://peraturan.bpk.go.id/',
 'img/phone.jpg', 'Perempuan berhijab memegang ponsel', 8, 'terbit', '2026-11-12 09:00:00', '2026-11-12 09:00:00', '2026-11-12 09:00:00')
ON DUPLICATE KEY UPDATE
 category_id = VALUES(category_id), author_id = VALUES(author_id), title = VALUES(title), excerpt = VALUES(excerpt),
 body = VALUES(body), sources = VALUES(sources), cover_path = VALUES(cover_path), cover_alt = VALUES(cover_alt),
 read_minutes = VALUES(read_minutes), status = VALUES(status), published_at = VALUES(published_at), updated_at = VALUES(updated_at);

COMMIT;
