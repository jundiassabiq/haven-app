-- Haven schema (MySQL 8 / MariaDB 10.4+). Import via phpMyAdmin or: php bin/db-reset.php
-- Sensitive columns ending in _enc hold AES-256-GCM ciphertext (base64), never plain text.

CREATE DATABASE IF NOT EXISTS haven_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE haven_v2;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS rate_limits, audit_logs, notifications, quiz_attempts, quiz_options, quiz_questions, quizzes,
  articles, article_categories, messages, bookings, companion_slots, case_notes, mail_queue, report_reminders, report_consents,
  report_status_logs, report_attachments, reports, services, password_resets, users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------- Module 1: accounts ----------
CREATE TABLE users (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name                VARCHAR(80)  NOT NULL,
  email               VARCHAR(190) NOT NULL,
  password_hash       VARCHAR(255) NOT NULL,
  role                ENUM('survivor','companion','admin') NOT NULL DEFAULT 'survivor',
  region              VARCHAR(100) NULL COMMENT 'Wilayah kerja pendamping',
  -- Area A (sdg5-improvements)
  child_trained       TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Pendamping terlatih kasus anak',
  allow_email         TINYINT(1)   NOT NULL DEFAULT 1,
  allow_whatsapp      TINYINT(1)   NOT NULL DEFAULT 0,
  safe_time           VARCHAR(120) NULL,
  is_active           TINYINT(1)   NOT NULL DEFAULT 1,
  session_version     INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Bumped to sign out all sessions',
  last_login_at       DATETIME     NULL,
  delete_requested_at DATETIME     NULL,
  created_at          DATETIME     NOT NULL,
  updated_at          DATETIME     NOT NULL,
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  token_hash  CHAR(64)     NOT NULL,
  expires_at  DATETIME     NOT NULL,
  used_at     DATETIME     NULL,
  created_at  DATETIME     NOT NULL,
  UNIQUE KEY uq_resets_token (token_hash),
  KEY idx_resets_user (user_id),
  CONSTRAINT fk_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Module 9: service directory (referenced by reports) ----------
CREATE TABLE services (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(160) NOT NULL,
  type          ENUM('hotline','uptd','polisi','rs','lbh','psikolog','kampus') NOT NULL,
  province      VARCHAR(60)  NOT NULL,
  city          VARCHAR(80)  NOT NULL,
  address       TEXT         NULL,
  phone         VARCHAR(30)  NULL,
  whatsapp      VARCHAR(30)  NULL,
  hours         VARCHAR(80)  NOT NULL DEFAULT '',
  is_24h        TINYINT(1)   NOT NULL DEFAULT 0,
  tags          VARCHAR(255) NOT NULL DEFAULT '',
  is_verified   TINYINT(1)   NOT NULL DEFAULT 0,
  verified_at   DATE         NULL,
  verified_note VARCHAR(255) NULL,
  created_at    DATETIME     NOT NULL,
  updated_at    DATETIME     NOT NULL,
  KEY idx_services_area (province, city),
  KEY idx_services_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Modules 2 to 4: reports & case management ----------
CREATE TABLE reports (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code                 CHAR(9)      NOT NULL COMMENT 'HV-XXXXXX',
  pin_hash             VARCHAR(255) NOT NULL,
  user_id              INT UNSIGNED NULL COMMENT 'NULL = anonim',
  category             ENUM('nonfisik','fisik','ksbe','pemaksaan','eksploitasi','belum-yakin',
                            'kdrt','kekerasan-pacaran','psikis','ekonomi','perkawinan-anak','perdagangan-orang') NOT NULL
                       COMMENT 'Sama dengan kunci Report::CATEGORIES',
  in_danger            TINYINT(1)   NOT NULL DEFAULT 0,
  ongoing              ENUM('ya','tidak','tidak-tahu') NOT NULL DEFAULT 'tidak-tahu',
  urgency              ENUM('tinggi','sedang','rendah') NOT NULL DEFAULT 'sedang',
  status               ENUM('diterima','ditinjau','didampingi','dirujuk','selesai','ditutup') NOT NULL DEFAULT 'diterima',
  province             VARCHAR(60)  NULL,
  city                 VARCHAR(80)  NULL,
  place_type           VARCHAR(60)  NULL,
  perpetrator_relation VARCHAR(60)  NULL,
  incident_date        DATE         NULL,
  chronology_enc       MEDIUMTEXT   NOT NULL,
  contact_channels     VARCHAR(60)  NOT NULL DEFAULT 'pesan',
  contact_enc          TEXT         NULL COMMENT 'JSON {email, wa}, encrypted',
  -- Area A (sdg5-improvements)
  reporter_type        ENUM('korban','saksi') NOT NULL DEFAULT 'korban' COMMENT 'Peran pelapor',
  victim_age_answer    ENUM('ya','tidak','tidak-yakin') NULL COMMENT 'Korban di bawah 18 tahun? NULL = laporan sebelum fitur ini',
  is_child             TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Kasus anak, diturunkan oleh ReportIntake::derive',
  victim_contact_enc   TEXT         NULL COMMENT 'Kontak korban dari saksi, encrypted',
  assigned_to          INT UNSIGNED NULL,
  service_id           INT UNSIGNED NULL COMMENT 'Layanan rujukan',
  closed_reason        TEXT         NULL,
  created_at           DATETIME     NOT NULL,
  updated_at           DATETIME     NOT NULL,
  UNIQUE KEY uq_reports_code (code),
  KEY idx_reports_status (status, urgency, created_at),
  KEY idx_reports_assigned (assigned_to, status),
  KEY idx_reports_user (user_id),
  KEY idx_reports_child (is_child, status),            -- Area A (sdg5-improvements)
  CONSTRAINT fk_reports_user     FOREIGN KEY (user_id)     REFERENCES users (id)    ON DELETE SET NULL,
  CONSTRAINT fk_reports_assignee FOREIGN KEY (assigned_to) REFERENCES users (id)    ON DELETE SET NULL,
  CONSTRAINT fk_reports_service  FOREIGN KEY (service_id)  REFERENCES services (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE report_attachments (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id         INT UNSIGNED NOT NULL,
  stored_name       CHAR(64)     NOT NULL COMMENT 'Random file name in storage/uploads',
  original_name_enc TEXT         NOT NULL,
  mime              VARCHAR(60)  NOT NULL,
  size_bytes        INT UNSIGNED NOT NULL,
  created_at        DATETIME     NOT NULL,
  UNIQUE KEY uq_attach_name (stored_name),
  CONSTRAINT fk_attach_report FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE report_status_logs (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id   INT UNSIGNED NOT NULL,
  from_status VARCHAR(20)  NULL,
  to_status   VARCHAR(20)  NOT NULL,
  actor_id    INT UNSIGNED NULL,
  note_enc    TEXT         NULL COMMENT 'Catatan untuk penyintas, encrypted',
  created_at  DATETIME     NOT NULL,
  KEY idx_statuslog_report (report_id, created_at),
  CONSTRAINT fk_statuslog_report FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE,
  CONSTRAINT fk_statuslog_actor  FOREIGN KEY (actor_id)  REFERENCES users (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Area A (sdg5-improvements): persetujuan korban atas tindak lanjut laporan saksi (Req 5.7, 5.8).
-- Satu baris per laporan. Tidak menyimpan identitas korban maupun isi laporan;
-- companion_id adalah pendamping yang mencatat persetujuan, bukan penyintas.
CREATE TABLE report_consents (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id    INT UNSIGNED NOT NULL,
  companion_id INT UNSIGNED NOT NULL COMMENT 'Pendamping yang mencatat persetujuan',
  created_at   DATETIME     NOT NULL,
  UNIQUE KEY uq_consent_report (report_id),
  CONSTRAINT fk_consent_report FOREIGN KEY (report_id)    REFERENCES reports (id) ON DELETE CASCADE,
  -- Tanpa ON DELETE: catatan siapa yang mencatat persetujuan tidak boleh hilang.
  CONSTRAINT fk_consent_user   FOREIGN KEY (companion_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Area A (sdg5-improvements): pengingat kasus mendesak yang dibuat bin/reminders.php (Req 6.3 sampai 6.6).
-- Satu baris per kasus per tipe per periode SLA. UNIQUE (report_id, type, period) plus INSERT IGNORE
-- membuat run berulang tidak menambah baris maupun notifikasi (Req 6.6).
-- Tidak menyimpan kronologi, isi notifikasi, penerima, maupun IP (Req 10.2, 10.4).
CREATE TABLE report_reminders (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id  INT UNSIGNED NOT NULL,
  type       ENUM('danger_unanswered','no_reply') NOT NULL COMMENT 'Sama dengan ReminderPlanner::TYPES',
  period     INT UNSIGNED NOT NULL COMMENT 'Periode SLA dari Sla::periodIndex, mulai 0',
  created_at DATETIME     NOT NULL,
  UNIQUE KEY uq_reminder (report_id, type, period),
  CONSTRAINT fk_reminder_report FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Area A (sdg5-improvements): email yang gagal dikirim, dicoba ulang bin/mail-queue.php (Req 6.11, 6.12).
-- failed_retries menghitung percobaan ULANG yang gagal: baris baru masuk dengan 0 dan
-- last_attempt_at = waktu percobaan pertama yang gagal, jadi nilai 5 berarti email dijatuhkan
-- (lihat MailQueue::decide). Alamat dan isi disimpan terenkripsi AES-256-GCM karena bisa memuat
-- data pribadi (Req 10.3). Tanpa kolom user id, IP, atau report id: antrean tidak boleh menjadi
-- jalan untuk menghubungkan email ke laporan anonim (Req 10.4). Subjek tidak terenkripsi karena
-- dibaca pengelola saat memeriksa antrean, dan MailPolicy::containsForbidden sudah memastikan
-- subjek tanpa kode laporan, PIN, maupun kronologi (Req 6.13).
CREATE TABLE mail_queue (
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

CREATE TABLE case_notes (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id  INT UNSIGNED NOT NULL,
  author_id  INT UNSIGNED NULL,
  title      VARCHAR(120) NULL,
  body_enc   TEXT         NOT NULL,
  created_at DATETIME     NOT NULL,
  KEY idx_notes_report (report_id, created_at),
  CONSTRAINT fk_notes_report FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE,
  CONSTRAINT fk_notes_author FOREIGN KEY (author_id) REFERENCES users (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Module 5: scheduling ----------
CREATE TABLE companion_slots (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  companion_id INT UNSIGNED      NOT NULL,
  starts_at    DATETIME          NOT NULL,
  duration_min SMALLINT UNSIGNED NOT NULL DEFAULT 45,
  mode         ENUM('suara','video','tatap') NOT NULL DEFAULT 'suara',
  location     VARCHAR(160)      NULL,
  created_at   DATETIME          NOT NULL,
  UNIQUE KEY uq_slot_time (companion_id, starts_at),
  CONSTRAINT fk_slots_companion FOREIGN KEY (companion_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bookings (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slot_id      INT UNSIGNED NOT NULL,
  report_id    INT UNSIGNED NOT NULL,
  status       ENUM('aktif','dibatalkan','selesai') NOT NULL DEFAULT 'aktif',
  cancelled_by ENUM('survivor','companion') NULL,
  created_at   DATETIME     NOT NULL,
  cancelled_at DATETIME     NULL,
  KEY idx_bookings_slot (slot_id, status),
  KEY idx_bookings_report (report_id, status),
  CONSTRAINT fk_bookings_slot   FOREIGN KEY (slot_id)   REFERENCES companion_slots (id) ON DELETE CASCADE,
  CONSTRAINT fk_bookings_report FOREIGN KEY (report_id) REFERENCES reports (id)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Module 6: secure messages ----------
CREATE TABLE messages (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id   INT UNSIGNED NOT NULL,
  sender_role ENUM('survivor','companion','system') NOT NULL,
  sender_id   INT UNSIGNED NULL,
  body_enc    TEXT         NOT NULL,
  read_at     DATETIME     NULL,
  created_at  DATETIME     NOT NULL,
  KEY idx_messages_thread (report_id, id),
  CONSTRAINT fk_messages_report FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE,
  CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Module 7: education ----------
CREATE TABLE article_categories (
  id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(40) NOT NULL,
  name VARCHAR(60) NOT NULL,
  UNIQUE KEY uq_cat_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE articles (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id  INT UNSIGNED     NOT NULL,
  author_id    INT UNSIGNED     NULL,
  title        VARCHAR(160)     NOT NULL,
  slug         VARCHAR(160)     NOT NULL,
  excerpt      VARCHAR(255)     NOT NULL,
  body         MEDIUMTEXT       NOT NULL,
  sources      TEXT             NOT NULL,
  cover_path   VARCHAR(255)     NULL,
  cover_alt    VARCHAR(200)     NOT NULL DEFAULT '',
  read_minutes TINYINT UNSIGNED NOT NULL DEFAULT 5,
  status       ENUM('draf','terbit') NOT NULL DEFAULT 'draf',
  published_at DATETIME         NULL,
  created_at   DATETIME         NOT NULL,
  updated_at   DATETIME         NOT NULL,
  UNIQUE KEY uq_articles_slug (slug),
  KEY idx_articles_pub (status, published_at),
  CONSTRAINT fk_articles_cat    FOREIGN KEY (category_id) REFERENCES article_categories (id),
  CONSTRAINT fk_articles_author FOREIGN KEY (author_id)   REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Module 11: notifications & audit ----------
CREATE TABLE notifications (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NULL COMMENT 'Penerima berakun',
  report_id  INT UNSIGNED NULL COMMENT 'Penerima anonim (lewat sesi kasus)',
  type       VARCHAR(30)  NOT NULL COMMENT 'status|pesan|jadwal|tugas|rujukan',
  title      VARCHAR(160) NOT NULL COMMENT 'Tidak pernah berisi isi pesan',
  link       VARCHAR(255) NOT NULL DEFAULT '',
  read_at    DATETIME     NULL,
  created_at DATETIME     NOT NULL,
  KEY idx_notif_user (user_id, read_at, id),
  KEY idx_notif_report (report_id, read_at, id),
  CONSTRAINT fk_notif_user   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE CASCADE,
  CONSTRAINT fk_notif_report FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_id    INT UNSIGNED NULL,
  actor_label VARCHAR(80)  NOT NULL,
  action      VARCHAR(40)  NOT NULL,
  object_type VARCHAR(30)  NULL,
  object_ref  VARCHAR(40)  NULL,
  detail      VARCHAR(255) NULL,
  ip          VARCHAR(45)  NOT NULL,
  user_agent  VARCHAR(255) NULL,
  created_at  DATETIME     NOT NULL,
  KEY idx_audit_time (created_at),
  KEY idx_audit_actor (actor_id, action),
  KEY idx_audit_object (object_type, object_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
  rl_key   CHAR(64)     NOT NULL PRIMARY KEY COMMENT 'sha256 of the limiter key',
  hits     INT UNSIGNED NOT NULL,
  reset_at INT UNSIGNED NOT NULL COMMENT 'Unix time'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
