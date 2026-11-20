-- Haven demo data. Import after schema.sql.
-- All demo accounts use the password: haven123
USE haven_v2;

-- ---------- Users (Task 3) ----------
-- Idempoten: id dan email dua-duanya unik, jadi memuat seed dua kali memperbarui
-- baris yang sama alih-alih berhenti dengan galat kunci ganda. Semua nilai di
-- daftar UPDATE tetap (termasuk created_at dan updated_at), jadi muat kedua
-- menghasilkan baris yang sama persis dengan muat pertama.
--
-- child_trained sengaja tidak ada di sini. Blok "-- AREA A" di akhir berkas yang
-- mengatur pendamping terlatih kasus anak, dan karena kolom itu tidak ikut di
-- daftar UPDATE, memuat ulang blok ini tidak pernah mematikan penandanya.
INSERT INTO users (id, name, email, password_hash, role, region, allow_email, allow_whatsapp, safe_time, created_at, updated_at) VALUES
 (1, 'Admin Haven',     'admin@demo.id', '$2y$10$0BUzX6IVDmwuOmiHaXo71uNPudbhIETCLKAwIxet5zNdm4vK31kxW', 'admin',     NULL,          1, 0, NULL, '2026-08-01 08:00:00', '2026-08-01 08:00:00'),
 (2, 'Dewi Anggraini',  'dewi@demo.id',  '$2y$10$0BUzX6IVDmwuOmiHaXo71uNPudbhIETCLKAwIxet5zNdm4vK31kxW', 'companion', 'Banyumas',    1, 0, NULL, '2026-08-01 08:00:00', '2026-08-01 08:00:00'),
 (3, 'Rani',            'rani@demo.id',  '$2y$10$0BUzX6IVDmwuOmiHaXo71uNPudbhIETCLKAwIxet5zNdm4vK31kxW', 'survivor',  NULL,          1, 0, 'Hari kerja setelah pukul 19.00', '2026-09-10 20:00:00', '2026-09-10 20:00:00'),
 (4, 'Sari Pratiwi',    'sari@demo.id',  '$2y$10$0BUzX6IVDmwuOmiHaXo71uNPudbhIETCLKAwIxet5zNdm4vK31kxW', 'companion', 'Purbalingga', 1, 0, NULL, '2026-08-01 08:00:00', '2026-08-01 08:00:00'),
 (5, 'Bayu Nugroho',    'bayu@demo.id',  '$2y$10$0BUzX6IVDmwuOmiHaXo71uNPudbhIETCLKAwIxet5zNdm4vK31kxW', 'companion', 'Banyumas',    1, 0, NULL, '2026-08-01 08:00:00', '2026-08-01 08:00:00'),
 (6, 'Laras Wulandari', 'laras@demo.id', '$2y$10$0BUzX6IVDmwuOmiHaXo71uNPudbhIETCLKAwIxet5zNdm4vK31kxW', 'companion', 'Semarang',    1, 0, NULL, '2026-08-01 08:00:00', '2026-08-01 08:00:00')
ON DUPLICATE KEY UPDATE
 name = VALUES(name), email = VALUES(email), password_hash = VALUES(password_hash), role = VALUES(role), region = VALUES(region),
 allow_email = VALUES(allow_email), allow_whatsapp = VALUES(allow_whatsapp), safe_time = VALUES(safe_time),
 created_at = VALUES(created_at), updated_at = VALUES(updated_at);

-- ---------- Services (Task 10) ----------
-- Only the two national numbers carry contacts. Every regional entry is UNVERIFIED and has no phone:
-- the team must call each institution and fill in contacts via Admin > Layanan before the demo.
--
-- Idempoten: id ditulis tetap supaya memuat seed dua kali memperbarui dua belas
-- baris yang sama, bukan menambah dua belas layanan kembar. Tanpa id, duplikat
-- itu ikut tertandai oleh blok "-- AREA B" di bawah dan panel rujukan anak bisa
-- menunjuk baris kembar. created_at tidak ikut di daftar UPDATE agar waktu muat
-- pertama tetap utuh.
INSERT INTO services (id, name, type, province, city, address, phone, whatsapp, hours, is_24h, tags, is_verified, verified_at, verified_note, created_at, updated_at) VALUES
 (1,  'SAPA 129 (Kementerian PPPA)', 'hotline', 'Nasional', 'Nasional', NULL, '129', '08111-129-129', '24 jam', 1, 'Pengaduan, Rujukan', 1, '2026-11-20', 'Hotline nasional resmi KemenPPPA. Cek ulang sebelum demo.', NOW(), NOW()),
 (2,  'Layanan darurat nasional', 'hotline', 'Nasional', 'Nasional', NULL, '112', NULL, '24 jam', 1, 'Darurat', 1, '2026-11-20', 'Nomor darurat nasional. Cek ulang sebelum demo.', NOW(), NOW()),
 (3,  'UPTD PPA Kabupaten Banyumas', 'uptd', 'Jawa Tengah', 'Banyumas', NULL, NULL, NULL, 'Senin sampai Jumat', 0, 'Pendampingan, Psikologis, Hukum', 0, NULL, NULL, NOW(), NOW()),
 (4,  'Unit PPA Satreskrim Polresta Banyumas', 'polisi', 'Jawa Tengah', 'Banyumas', NULL, NULL, NULL, '24 jam', 1, 'Laporan polisi', 0, NULL, NULL, NOW(), NOW()),
 (5,  'RSUD Prof. Dr. Margono Soekarjo', 'rs', 'Jawa Tengah', 'Banyumas', NULL, NULL, NULL, '24 jam (IGD)', 1, 'Medis, Visum', 0, NULL, NULL, NOW(), NOW()),
 (6,  'Satgas PPKPT Universitas Jenderal Soedirman', 'kampus', 'Jawa Tengah', 'Banyumas', NULL, NULL, NULL, 'Hari kerja', 0, 'Kampus, Pendampingan', 0, NULL, NULL, NOW(), NOW()),
 (7,  'Layanan psikologi kampus (contoh)', 'psikolog', 'Jawa Tengah', 'Banyumas', NULL, NULL, NULL, 'Dengan janji', 0, 'Konseling', 0, NULL, NULL, NOW(), NOW()),
 (8,  'UPTD PPA Kabupaten Purbalingga', 'uptd', 'Jawa Tengah', 'Purbalingga', NULL, NULL, NULL, 'Senin sampai Jumat', 0, 'Pendampingan', 0, NULL, NULL, NOW(), NOW()),
 (9,  'UPTD PPA Kabupaten Cilacap', 'uptd', 'Jawa Tengah', 'Cilacap', NULL, NULL, NULL, 'Senin sampai Jumat', 0, 'Pendampingan', 0, NULL, NULL, NOW(), NOW()),
 (10, 'UPTD PPA Provinsi Jawa Tengah', 'uptd', 'Jawa Tengah', 'Semarang', NULL, NULL, NULL, 'Senin sampai Jumat', 0, 'Pendampingan, Rumah aman', 0, NULL, NULL, NOW(), NOW()),
 (11, 'LBH APIK Semarang', 'lbh', 'Jawa Tengah', 'Semarang', NULL, NULL, NULL, 'Hari kerja', 0, 'Hukum, Gratis', 0, NULL, NULL, NOW(), NOW()),
 (12, 'UPTD PPA Provinsi DI Yogyakarta', 'uptd', 'DI Yogyakarta', 'Yogyakarta', NULL, NULL, NULL, 'Senin sampai Jumat', 0, 'Pendampingan', 0, NULL, NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE
 name = VALUES(name), type = VALUES(type), province = VALUES(province), city = VALUES(city), address = VALUES(address),
 phone = VALUES(phone), whatsapp = VALUES(whatsapp), hours = VALUES(hours), is_24h = VALUES(is_24h), tags = VALUES(tags),
 is_verified = VALUES(is_verified), verified_at = VALUES(verified_at), verified_note = VALUES(verified_note), updated_at = VALUES(updated_at);

-- AREA B ---------- Layanan panel rujukan anak dan opsi darurat (Tugas 4.9) ----------
-- Req 4.5: Service::findChildServices() mencari tag panel-anak:uptd, panel-anak:kpai,
--   dan panel-anak:sapa129 (bukan nama layanan), jadi blok ini yang memasang tag itu.
-- Req 8.12: Emergency::options membaca layanan terverifikasi, jadi entri 112 dan 129
--   harus ada dan terverifikasi.
--
-- Status verifikasi sengaja dibedakan. Hanya nomor yang sudah terbit resmi dan
-- dipakai luas yang ditandai terverifikasi di sini: 112 (nomor darurat nasional)
-- dan SAPA 129 (KemenPPPA). KPAI dan UPTD PPA tidak ditandai terverifikasi dan
-- tidak diberi nomor: tim harus menelepon dulu lalu mengisinya di Admin > Layanan.
-- Sampai itu dilakukan, panel menampilkan namanya dengan label "Perlu verifikasi"
-- tanpa kontak (Req 1.1, 1.2, 4.6). Jangan menambah nomor di sini tanpa menelepon.
--
-- Blok ini idempoten. UPDATE memakai nilai tetap, dan tiap INSERT dijaga
-- NOT EXISTS, jadi memuat seed dua kali tidak menambah baris dan tidak mengubah
-- hasil. INSERT hanya bekerja sebagai cadangan bila blok Services di atas diubah
-- atau dihapus.

-- 1. Tag panel anak pada layanan yang sudah ada di blok Services di atas.
UPDATE services SET tags = 'Pengaduan, Rujukan, Anak, panel-anak:sapa129', updated_at = NOW()
  WHERE type = 'hotline' AND province = 'Nasional' AND phone = '129';

UPDATE services SET tags = 'Pendampingan, Rumah aman, Anak, panel-anak:uptd', updated_at = NOW()
  WHERE type = 'uptd' AND name = 'UPTD PPA Provinsi Jawa Tengah';

-- 2. KPAI: slot ketiga panel, belum ada di blok Services. Belum terverifikasi dan
--    tanpa kontak, sesuai catatan di atas.
INSERT INTO services (name, type, province, city, address, phone, whatsapp, hours, is_24h, tags, is_verified, verified_at, verified_note, created_at, updated_at)
SELECT 'Komisi Perlindungan Anak Indonesia (KPAI)', 'hotline', 'Nasional', 'Nasional', NULL, NULL, NULL, 'Hari kerja', 0, 'Anak, Pengaduan, panel-anak:kpai', 0, NULL, NULL, NOW(), NOW()
  FROM (SELECT 1) AS b
 WHERE NOT EXISTS (SELECT 1 FROM services WHERE tags LIKE '%panel-anak:kpai%');

-- 3. Cadangan: pastikan tiap slot panel dan tiap nomor darurat tetap ada walau
--    blok Services di atas berubah. Tidak jalan pada seed utuh karena langkah 1
--    sudah memasang tagnya.
INSERT INTO services (name, type, province, city, address, phone, whatsapp, hours, is_24h, tags, is_verified, verified_at, verified_note, created_at, updated_at)
SELECT 'SAPA 129 (Kementerian PPPA)', 'hotline', 'Nasional', 'Nasional', NULL, '129', '08111-129-129', '24 jam', 1, 'Pengaduan, Rujukan, Anak, panel-anak:sapa129', 1, CURDATE(), 'Hotline nasional resmi KemenPPPA. Cek ulang sebelum demo.', NOW(), NOW()
  FROM (SELECT 1) AS b
 WHERE NOT EXISTS (SELECT 1 FROM services WHERE tags LIKE '%panel-anak:sapa129%');

INSERT INTO services (name, type, province, city, address, phone, whatsapp, hours, is_24h, tags, is_verified, verified_at, verified_note, created_at, updated_at)
SELECT 'UPTD PPA Provinsi Jawa Tengah', 'uptd', 'Jawa Tengah', 'Semarang', NULL, NULL, NULL, 'Senin sampai Jumat', 0, 'Pendampingan, Rumah aman, Anak, panel-anak:uptd', 0, NULL, NULL, NOW(), NOW()
  FROM (SELECT 1) AS b
 WHERE NOT EXISTS (SELECT 1 FROM services WHERE tags LIKE '%panel-anak:uptd%');

INSERT INTO services (name, type, province, city, address, phone, whatsapp, hours, is_24h, tags, is_verified, verified_at, verified_note, created_at, updated_at)
SELECT 'Layanan darurat nasional', 'hotline', 'Nasional', 'Nasional', NULL, '112', NULL, '24 jam', 1, 'Darurat', 1, CURDATE(), 'Nomor darurat nasional. Cek ulang sebelum demo.', NOW(), NOW()
  FROM (SELECT 1) AS b
 WHERE NOT EXISTS (SELECT 1 FROM services WHERE province = 'Nasional' AND phone = '112');
-- AREA B selesai.

-- AREA A ---------- Pendamping terlatih kasus anak (Tugas 7.9) ----------
-- Req 4.9: Assignment::candidates hanya menawarkan pendamping child_trained = 1
--   yang wilayah kerjanya sama dengan wilayah laporan, jadi demo kasus anak
--   butuh minimal satu pendamping terlatih di wilayah yang dipakai laporan.
-- Req 4.10: jalur peringatan baru terlihat kalau ADA wilayah tanpa pendamping
--   terlatih. Karena itu hanya Banyumas yang punya pendamping terlatih di sini;
--   Purbalingga, Semarang, dan DI Yogyakarta sengaja dibiarkan tanpa, supaya
--   peringatan "tidak ada pendamping terlatih" dan konfirmasi Admin bisa
--   ditunjukkan apa adanya.
--
-- Keadaan setelah blok ini (lihat Assignment.php):
--   Dewi Anggraini   Banyumas      terlatih    kasus anak Banyumas -> jalur Req 4.9
--   Nindya Prameswari Banyumas     terlatih    kandidat kedua, jadi daftarnya bukan satu nama
--   Bayu Nugroho     Banyumas      tidak       tersaring walau wilayahnya sama
--   Sari Pratiwi     Purbalingga   tidak       kasus anak di sana -> jalur Req 4.10
--   Laras Wulandari  Semarang      tidak       sama
--   (tidak ada pendamping di DI Yogyakarta)    wilayah tanpa pendamping sama sekali
--
-- LAPORAN CONTOH TIDAK ADA DI BERKAS INI. Kronologi laporan wajib tersimpan
-- sebagai ciphertext AES-256-GCM dengan APP_KEY instalasi ini (Req 10.3), dan
-- SQL murni tidak bisa menghasilkannya. Menuliskan kronologi apa adanya ke
-- chronology_enc akan menaruh teks sensitif sebagai plaintext di kolom
-- terenkripsi dan membuat Report::chronology gagal mendekripsi. Jadi laporan
-- contoh kategori baru, laporan saksi, dan kasus anak dibuat
-- `bin/seed-demo.php` lewat Report::create, yang memakai Crypto::enc dan
-- ReportIntake::derive seperti form lapor:
--   php bin/db-reset.php && php bin/seed-demo.php
--
-- Blok ini idempoten: UPDATE memakai nilai tetap dan INSERT dijaga
-- ON DUPLICATE KEY UPDATE, jadi memuat seed dua kali tidak menambah baris dan
-- tidak mengubah hasil.

-- 1. Pendamping terlatih di Banyumas. Tetap satu baris walau seed dimuat ulang:
--    id 7 dan email keduanya unik.
INSERT INTO users (id, name, email, password_hash, role, region, child_trained, allow_email, allow_whatsapp, safe_time, created_at, updated_at) VALUES
 (7, 'Nindya Prameswari', 'nindya@demo.id', '$2y$10$0BUzX6IVDmwuOmiHaXo71uNPudbhIETCLKAwIxet5zNdm4vK31kxW', 'companion', 'Banyumas', 1, 1, 0, NULL, '2026-08-01 08:00:00', '2026-08-01 08:00:00')
ON DUPLICATE KEY UPDATE
 name = VALUES(name), email = VALUES(email), password_hash = VALUES(password_hash), role = VALUES(role), region = VALUES(region),
 child_trained = VALUES(child_trained), allow_email = VALUES(allow_email), allow_whatsapp = VALUES(allow_whatsapp),
 safe_time = VALUES(safe_time), created_at = VALUES(created_at), updated_at = VALUES(updated_at);

-- 2. Dewi mendampingi kasus demo tetap HV-7Q4K2M, jadi ia juga ditandai
--    terlatih. Dua nama terlatih di Banyumas membuat daftar kandidat Req 4.9
--    terlihat sebagai pilihan, bukan satu nama yang terpaksa.
UPDATE users SET child_trained = 1, updated_at = '2026-08-01 08:00:00'
  WHERE email = 'dewi@demo.id' AND role = 'companion';

-- 3. Tiga pendamping demo sisanya dipastikan TIDAK terlatih. Ditulis tegas
--    supaya keadaan demo sama setelah seed dimuat ulang, termasuk bila penanda
--    ini pernah dicentang lewat Admin > Pengguna saat uji coba. Hanya tiga akun
--    demo ini yang disentuh, bukan pendamping lain yang dibuat sendiri oleh tim.
UPDATE users SET child_trained = 0, updated_at = '2026-08-01 08:00:00'
  WHERE role = 'companion' AND email IN ('sari@demo.id', 'bayu@demo.id', 'laras@demo.id');
-- AREA A selesai.
