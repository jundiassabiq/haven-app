-- Haven migration: sdg5-improvements
-- Untuk database yang SUDAH berisi data. Database baru cukup pakai: php bin/db-reset.php
--
-- Pilih database Haven lebih dulu, karena berkas ini sengaja tidak memuat USE
-- maupun CREATE DATABASE: nama database berbeda di tiap instalasi (DB_NAME di .env).
-- Semua pernyataan berjalan di database yang sedang dipilih.
--
-- Jalankan dengan salah satu cara (ganti nama_database dengan DB_NAME Anda):
--   mysql -u root nama_database < database/migrations/2026-sdg5.sql
--   atau pilih database Haven di phpMyAdmin, lalu impor berkas ini.
--
-- Berkas ini idempoten: menjalankannya dua kali menghasilkan skema yang sama dan
-- tidak mengubah satu pun baris yang sudah ada. Penambahan kolom dibungkus
-- pemeriksaan information_schema (bukan ADD COLUMN IF NOT EXISTS) supaya berjalan
-- di MySQL 8 maupun MariaDB 10.4+.

-- ---------- Area A, Requirement 2: enam kategori laporan baru ----------
-- Nilai enum baru hanya ditambahkan di akhir daftar, sehingga indeks enum laporan
-- lama tidak bergeser dan nilai category tiap laporan tetap sama.
ALTER TABLE reports
  MODIFY COLUMN category ENUM('nonfisik','fisik','ksbe','pemaksaan','eksploitasi','belum-yakin',
                              'kdrt','kekerasan-pacaran','psikis','ekonomi','perkawinan-anak','perdagangan-orang') NOT NULL
                         COMMENT 'Sama dengan kunci Report::CATEGORIES';

-- ---------- Area A, Requirement 4 dan 5: peran pelapor, usia korban, kasus anak ----------
-- Tiap kolom punya default atau boleh NULL, jadi baris lama tidak berubah nilainya
-- (Req 2.4): laporan lama tetap reporter_type = 'korban', is_child = 0,
-- victim_age_answer = NULL, victim_contact_enc = NULL.

-- users.child_trained: pendamping terlatih kasus anak (Req 4.9).
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'child_trained') = 0,
  'ALTER TABLE users ADD COLUMN child_trained TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''Pendamping terlatih kasus anak'' AFTER region',
  'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- reports.reporter_type: korban atau saksi (Req 5.2).
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'reporter_type') = 0,
  'ALTER TABLE reports ADD COLUMN reporter_type ENUM(''korban'',''saksi'') NOT NULL DEFAULT ''korban'' COMMENT ''Peran pelapor'' AFTER contact_enc',
  'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- reports.victim_age_answer: jawaban "korban di bawah 18 tahun?" (Req 4.1).
-- NULL dipakai laporan yang dibuat sebelum pertanyaan ini ada.
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'victim_age_answer') = 0,
  'ALTER TABLE reports ADD COLUMN victim_age_answer ENUM(''ya'',''tidak'',''tidak-yakin'') NULL COMMENT ''Korban di bawah 18 tahun? NULL = laporan sebelum fitur ini'' AFTER reporter_type',
  'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- reports.is_child: penanda kasus anak, diturunkan ReportIntake::derive (Req 4.3).
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'is_child') = 0,
  'ALTER TABLE reports ADD COLUMN is_child TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''Kasus anak, diturunkan oleh ReportIntake::derive'' AFTER victim_age_answer',
  'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- reports.victim_contact_enc: kontak korban yang diberikan saksi, AES-256-GCM lewat
-- Crypto::enc, tidak pernah plaintext (Req 5.9).
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'victim_contact_enc') = 0,
  'ALTER TABLE reports ADD COLUMN victim_contact_enc TEXT NULL COMMENT ''Kontak korban dari saksi, encrypted'' AFTER is_child',
  'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- idx_reports_child: filter kasus anak di antrean admin (Req 4.8).
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reports' AND INDEX_NAME = 'idx_reports_child') = 0,
  'ALTER TABLE reports ADD KEY idx_reports_child (is_child, status)',
  'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := NULL;

-- ---------- Area A, Requirement 5: persetujuan korban untuk laporan saksi ----------
-- Tabel baru, jadi menjalankan berkas ini dua kali tidak mengubah apa pun.
-- Tanpa ON DELETE pada companion_id: catatan siapa yang mencatat persetujuan
-- tidak boleh hilang. Baris ikut terhapus bila laporannya dihapus.
CREATE TABLE IF NOT EXISTS report_consents (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id    INT UNSIGNED NOT NULL,
  companion_id INT UNSIGNED NOT NULL COMMENT 'Pendamping yang mencatat persetujuan',
  created_at   DATETIME     NOT NULL,
  UNIQUE KEY uq_consent_report (report_id),
  CONSTRAINT fk_consent_report FOREIGN KEY (report_id)    REFERENCES reports (id) ON DELETE CASCADE,
  CONSTRAINT fk_consent_user   FOREIGN KEY (companion_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Area A, Requirement 6: pengingat kasus mendesak ----------
-- Tabel baru, jadi menjalankan berkas ini dua kali tidak mengubah apa pun.
-- UNIQUE (report_id, type, period) adalah pagar idempotensi bin/reminders.php
-- (Req 6.6): satu pengingat per kasus per tipe per periode SLA. Baris ikut
-- terhapus bila laporannya dihapus. Tidak menyimpan kronologi, isi notifikasi,
-- penerima, maupun IP (Req 10.2, 10.4).
CREATE TABLE IF NOT EXISTS report_reminders (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id  INT UNSIGNED NOT NULL,
  type       ENUM('danger_unanswered','no_reply') NOT NULL COMMENT 'Sama dengan ReminderPlanner::TYPES',
  period     INT UNSIGNED NOT NULL COMMENT 'Periode SLA dari Sla::periodIndex, mulai 0',
  created_at DATETIME     NOT NULL,
  UNIQUE KEY uq_reminder (report_id, type, period),
  CONSTRAINT fk_reminder_report FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Area A, Requirement 6: antrean email yang gagal dikirim ----------
-- Tabel baru, jadi menjalankan berkas ini dua kali tidak mengubah apa pun.
-- failed_retries menghitung percobaan ULANG yang gagal: baris baru masuk dengan
-- 0 dan last_attempt_at = waktu percobaan pertama yang gagal, jadi nilai 5
-- berarti email dijatuhkan (lihat MailQueue::decide dan bin/mail-queue.php).
-- Alamat dan isi disimpan terenkripsi AES-256-GCM (Req 10.3). Tidak ada kolom
-- user id, IP, maupun report id: antrean tidak boleh menjadi jalan untuk
-- menghubungkan email ke laporan anonim (Req 10.4). Subjek tidak terenkripsi
-- karena dibaca pengelola saat memeriksa antrean, dan
-- MailPolicy::containsForbidden sudah memastikan subjek tanpa kode laporan,
-- PIN, maupun kronologi (Req 6.13).
CREATE TABLE IF NOT EXISTS mail_queue (
  id              INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
  to_email_enc    TEXT             NOT NULL COMMENT 'Alamat penerima, AES-256-GCM',
  type            VARCHAR(40)      NOT NULL COMMENT 'Tipe MailPolicy: password_reset|invitation|general',
  subject         VARCHAR(190)     NOT NULL,
  body_enc        MEDIUMTEXT       NOT NULL COMMENT 'Isi email, AES-256-GCM',
  failed_retries  TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Percobaan ulang yang gagal, 0 sampai MailQueue::MAX_RETRY',
  last_attempt_at DATETIME         NOT NULL,
  created_at      DATETIME         NOT NULL,
  KEY idx_mail_due (last_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
