# Implementation Plan: sdg5-improvements

## Overview

Implementasi memakai PHP vanilla MVC + MySQL yang sudah ada, dengan logika murni di `app/core/rules/*.php` agar bisa diuji tanpa DB. Tugas dikelompokkan per area supaya dua orang bisa bekerja paralel di cabang berbeda:

- **Area A (Pelapor dan Kasus)**: Requirement 2, 4, 5, 6 (tugas 2, 6, 7, 8, 9).
- **Area B (Konten, Layanan, Publik)**: Requirement 1, 3, 7, 8, 9 (tugas 4, 11, 12, 13, 14).
- **Bersama**: tugas 1 (infrastruktur tes), checkpoint, dan Requirement 10 (diterapkan di tiap tugas).

Urutan merge yang disarankan: (1) A: kategori + `Sla` (tugas 2, checkpoint 3), (2) B: `Service` (tugas 4, checkpoint 5), (3) A: sisanya (tugas 6 sampai 9, checkpoint 10), (4) B: `Stats` dan sisanya (tugas 11 sampai 14). Tugas B bagian (4) boleh dikerjakan paralel setelah checkpoint 5, tetapi di-merge terakhir. Perubahan `database/schema.sql` dan `database/seed.sql` dipisah per blok `-- Area A` / `-- Area B`.

## Tasks

- [x] 1. Siapkan kerangka tes bersama
  - Tambahkan PHPUnit dan `giorgiosironi/eris` (Composer hanya untuk dev), `phpunit.xml`, `tests/bootstrap.php` yang memuat `app/bootstrap.php` dan autoload `app/core/rules/*`
  - Buat `tests/Support/` untuk helper DB uji `haven_test` (dibuat lewat `bin/db-reset.php`)
  - Setiap tes properti memakai minimal 100 iterasi dan komentar tag `Feature: sdg5-improvements, Property N: ...`
  - _Requirements: 10.12_

- [x] 2. Area A: kategori baru dan aturan SLA (merge 1)
  - [x] 2.1 Perluas kategori laporan
    - Tambah enam kategori (`kdrt`, `kekerasan-pacaran`, `psikis`, `ekonomi`, `perkawinan-anak`, `perdagangan-orang`) ke `Report::CATEGORIES` dan `Report::CATEGORY_DESC` (label dan deskripsi satu kalimat, Bahasa Indonesia)
    - Perluas enum `reports.category` di `database/schema.sql` (12 nilai, pola slug `[a-z0-9_-]{1,40}`) dan buat `database/migrations/2026-sdg5.sql` idempoten yang hanya menambah nilai enum di akhir
    - Tampilkan semua kategori di form lapor dan filter kategori di `admin/queue.php`, validasi kategori tidak sah dengan pesan dan nilai lama dipertahankan
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 2.8, 2.11_

  - [ ]* 2.2 Tulis tes unit konsistensi kategori
    - Periksa kunci `CATEGORIES`, `CATEGORY_DESC`, dan nilai enum di `schema.sql` sama persis
    - Periksa batas panjang slug, label, dan deskripsi
    - _Requirements: 2.2, 2.3, 2.11_

  - [x] 2.3 Implementasi `Sla` (pure)
    - Buat `app/core/rules/Sla.php` dengan `hours`, `isOverdue`, `periodIndex`
    - Tambah `SLA_DANGER_HOURS` dan `SLA_DEFAULT_HOURS` ke `.env.example`, default 2 dan 48 bila nilai bukan bilangan bulat positif
    - _Requirements: 6.1, 6.5, 6.8_

  - [ ]* 2.4 Tulis tes properti untuk jam SLA
    - **Property 16: Jam SLA dari konfigurasi**
    - **Validates: Requirements 6.1**

  - [ ]* 2.5 Tulis tes properti untuk lencana lewat SLA
    - **Property 17: Lencana "Lewat SLA"**
    - **Validates: Requirements 6.8**

- [x] 3. Checkpoint - Merge 1 (Area A: kategori + Sla)
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. Area B: verifikasi dan penyembunyian nomor layanan (merge 2)
  - [x] 4.1 Implementasi `ServiceRules` (pure)
    - Buat `app/core/rules/ServiceRules.php`: `publicView`, `verificationErrors`, `nextVerified`, `isStale` (lebih dari 180 hari), `formatDate` (DD-MM-YYYY), `unverifiedCounts`
    - Pesan galat dalam Bahasa Indonesia
    - _Requirements: 1.1, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9, 1.11_

  - [ ]* 4.2 Tulis tes properti untuk tampilan publik layanan
    - **Property 1: Tampilan publik layanan menyembunyikan nomor jika belum terverifikasi**
    - **Validates: Requirements 1.1, 1.11**

  - [ ]* 4.3 Tulis tes properti untuk validasi verifikasi
    - **Property 2: Validasi verifikasi layanan**
    - **Validates: Requirements 1.3, 1.4, 1.5**

  - [ ]* 4.4 Tulis tes properti untuk reset verifikasi
    - **Property 3: Mengubah kontak mereset verifikasi**
    - **Validates: Requirements 1.6**

  - [ ]* 4.5 Tulis tes properti untuk batas 180 hari
    - **Property 4: Batas 180 hari untuk "Perlu dicek ulang"**
    - **Validates: Requirements 1.7**

  - [ ]* 4.6 Tulis tes properti untuk format tanggal
    - **Property 5: Format tanggal DD-MM-YYYY**
    - **Validates: Requirements 1.8**

  - [ ]* 4.7 Tulis tes properti untuk hitungan layanan belum terverifikasi
    - **Property 6: Hitungan layanan belum terverifikasi per tipe**
    - **Validates: Requirements 1.9**

  - [x] 4.8 Hubungkan `ServiceRules` ke model, controller, dan view
    - `Service::publicArray` dan `save` memakai `publicView` dan `nextVerified`, reset `is_verified`/`verified_at` dalam satu `UPDATE`, tolak simpan dengan data lama utuh bila validasi gagal
    - `Audit::log('service.save', 'service', id, "verifikasi: lama→baru")` dalam request yang sama
    - `services/index.php`, `case/*`, `report/sent.php` memakai `publicView` ("Perlu verifikasi" tanpa nomor, tanggal verifikasi DD-MM-YYYY di samping nomor)
    - `admin/services.php`: label "Perlu dicek ulang" dan hitungan belum terverifikasi per tipe
    - Tambah `Service::findChildServices()` yang mengembalikan kunci `uptd_ppa_anak`, `kpai`, `sapa_129` berdasarkan tag `panel-anak:*`
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9, 1.10, 1.11, 10.5_

  - [x] 4.9 Tambah seed layanan (blok `-- AREA B` di `database/seed.sql`)
    - Layanan terverifikasi SAPA 129, UPTD PPA anak, KPAI dengan tag `panel-anak:sapa129|uptd|kpai`, dan entri 112
    - _Requirements: 4.5, 8.12_

  - [ ]* 4.10 Tulis tes unit layanan
    - Label "Perlu verifikasi", audit log tersimpan, `findChildServices` mengembalikan tiga entri
    - _Requirements: 1.2, 1.10_

- [x] 5. Checkpoint - Merge 2 (Area B: Service)
  - Ensure all tests pass, ask the user if questions arise.

- [x] 6. Area A: intake laporan (peran pelapor, usia, kasus anak)
  - [x] 6.1 Perubahan skema dan migrasi Area A
    - Tambah `users.child_trained`, `reports.reporter_type`, `victim_age_answer`, `is_child`, `victim_contact_enc`, dan `idx_reports_child` di `schema.sql` (blok `-- Area A`) dan di `database/migrations/2026-sdg5.sql` dengan default agar baris lama tidak berubah
    - _Requirements: 2.4, 4.3, 5.2, 5.9, 10.12_

  - [x] 6.2 Implementasi `ReportIntake` (pure)
    - Buat `app/core/rules/ReportIntake.php`: `derive` (`is_child`, urgensi) dan `intakeErrors` (kategori, usia, peran)
    - `is_child` benar bila usia `ya`/`tidak-yakin` atau kategori `perkawinan-anak`; urgensi `tinggi` bila `is_child` atau `perdagangan-orang`
    - _Requirements: 2.6, 2.9, 2.10, 4.1, 4.2, 4.3, 4.7, 5.1_

  - [ ]* 6.3 Tulis tes properti untuk penurunan status anak
    - **Property 7: Penurunan status anak dan urgensi**
    - **Validates: Requirements 2.9, 2.10, 4.3, 4.7**

  - [ ]* 6.4 Tulis tes properti untuk validasi pilihan wajib
    - **Property 8: Pilihan wajib ditolak bila tidak valid**
    - **Validates: Requirements 2.6, 4.2, 5.1**

  - [x] 6.5 Hubungkan intake ke `Report::create`, `ReportController`, dan form
    - Form `report/form.php`: pertanyaan usia (tiga opsi), pilihan peran korban/saksi, kontak korban opsional (maks 255 karakter) untuk saksi
    - `Report::create` menyimpan `reporter_type`, `is_child`, `victim_age_answer`, `victim_contact_enc` (`Crypto::enc`), kode dan PIN dengan format sama untuk saksi, `user_id` kosong untuk mode anonim tanpa IP/email di kolom baru
    - Validasi CSRF dan `RateLimiter` dengan ambang yang sama untuk saksi, form dirender ulang dengan nilai lama kecuali PIN
    - `Audit::log` "Child_Case created" tanpa kronologi, notifikasi saksi bahwa Haven tidak bisa menghubungi korban tanpa persetujuan di `report/sent.php`
    - _Requirements: 2.9, 2.10, 4.1, 4.2, 4.3, 4.7, 4.11, 5.1, 5.2, 5.3, 5.4, 5.5, 5.9, 5.10, 5.11, 10.2, 10.3, 10.4, 10.6, 10.7, 10.8, 10.9_

  - [ ]* 6.6 Tulis tes properti untuk format kode laporan dan PIN
    - **Property 12: Format kode laporan dan PIN**
    - **Validates: Requirements 5.3**

  - [ ]* 6.7 Tulis tes unit alur form lapor
    - Penolakan CSRF (419) dan rate limit (429), nilai lama dipertahankan, kontak korban tidak tersimpan plaintext, migrasi tidak mengubah laporan lama
    - _Requirements: 2.4, 5.9, 5.10, 5.11, 10.8, 10.9_

- [x] 7. Area A: persetujuan saksi, penugasan anak, panel rujukan, antrean
  - [x] 7.1 Implementasi `ConsentGuard` dan persetujuan korban
    - Buat `app/core/rules/ConsentGuard.php`, tabel `report_consents` di `schema.sql`, panggil di `CaseWorkflow::transition` sebelum menulis status
    - Route `POST /pendamping/kasus/{kode}/persetujuan` (CSRF, hanya pendamping tertuju) menyimpan `companion_id` dan waktu, plus `Audit::log`
    - Label "Dilaporkan oleh saksi" di antrean dan detail kasus
    - _Requirements: 5.6, 5.7, 5.8, 10.5, 10.6_

  - [ ]* 7.2 Tulis tes properti untuk persetujuan korban
    - **Property 13: Persetujuan korban sebelum rujukan saksi**
    - **Validates: Requirements 5.8**

  - [x] 7.3 Implementasi `Assignment` (pure)
    - Buat `app/core/rules/Assignment.php`: `candidates` mengembalikan pendamping `child_trained` di wilayah laporan, atau peringatan + `needsConfirm` dan semua pendamping dengan wilayah sama di depan
    - _Requirements: 4.9, 4.10_

  - [ ]* 7.4 Tulis tes properti untuk kandidat penugasan anak
    - **Property 11: Kandidat penugasan kasus anak**
    - **Validates: Requirements 4.9, 4.10**

  - [x] 7.5 Implementasi `ChildReferral` dan tampilkan panel
    - Buat `app/core/rules/ChildReferral.php` dengan `panel` yang selalu tiga entri, entri tak terverifikasi tanpa kontak dan berlabel "Perlu verifikasi"
    - Tampilkan di `report/sent.php` dan halaman lacak untuk `is_child`, data dari `Service::findChildServices()`
    - _Requirements: 4.4, 4.5, 4.6_

  - [ ]* 7.6 Tulis tes properti untuk panel rujukan anak
    - **Property 10: Panel rujukan anak**
    - **Validates: Requirements 4.5, 4.6**

  - [x] 7.7 Antrean admin: filter, lencana, dan penugasan anak
    - `admin/queue.php`: lencana "Anak" tepat pada `is_child`, filter kasus anak dan filter kategori lewat `Report` dan `AdminCaseController`
    - Penugasan memakai `Assignment::candidates`, `CaseWorkflow` menolak penugasan tanpa `confirm=1` saat `needsConfirm`
    - Tambah penanda `child_trained` di form pengguna pendamping (`admin/users.php`, `AdminUserController`)
    - _Requirements: 2.7, 2.8, 4.8, 4.9, 4.10_

  - [ ]* 7.8 Tulis tes properti untuk filter antrean
    - **Property 9: Filter antrean**
    - **Validates: Requirements 2.8, 4.8**

  - [x] 7.9 Seed Area A (blok `-- AREA A` di `database/seed.sql`)
    - Pendamping contoh dengan `child_trained = 1` dan laporan contoh kategori baru, saksi, dan anak
    - _Requirements: 2.1, 4.9, 5.2_

- [x] 8. Area A: SLA balasan dan pengingat
  - [x] 8.1 Implementasi `ReminderPlanner` dan `bin/reminders.php`
    - Tabel `report_reminders` dengan `UNIQUE (report_id, type, period)` di `schema.sql`
    - `app/core/rules/ReminderPlanner.php`: tipe `danger_unanswered` dan `no_reply`, penerima sesuai aturan, penyintas anonim tanpa email hanya notifikasi dalam aplikasi
    - `bin/reminders.php`: satu transaksi, `INSERT IGNORE`, `Notifier`, keluar 0 bila sukses, bila DB gagal tulis `Logger` dan keluar 1 tanpa pengingat
    - _Requirements: 6.3, 6.4, 6.5, 6.6, 6.7, 6.15, 6.16, 10.2_

  - [ ]* 8.2 Tulis tes properti untuk penerima pengingat
    - **Property 18: Penerima pengingat**
    - **Validates: Requirements 6.3, 6.4, 6.15**

  - [ ]* 8.3 Tulis tes properti untuk idempotensi pengingat
    - **Property 19: Satu pengingat per periode dan idempotensi**
    - **Validates: Requirements 6.5, 6.6**

  - [x] 8.4 Tampilkan SLA dan lencana "Lewat SLA"
    - `report/sent.php` dan halaman lacak menampilkan SLA dalam jam, `admin/queue.php` menampilkan lencana lewat `Sla::isOverdue`
    - _Requirements: 6.2, 6.8_

  - [ ]* 8.5 Tulis tes integrasi skrip pengingat
    - Kode keluar 0 saat sukses, non-nol saat DB tidak tersedia dan tanpa pengingat, dua kali run dalam 60 detik tidak menambah baris
    - _Requirements: 6.6, 6.7, 6.16_

- [x] 9. Area A: email SMTP, antrean ulang, dan privasi
  - [x] 9.1 Implementasi `MailPolicy` dan `MailQueue` (pure)
    - `app/core/rules/MailPolicy.php`: `mode`, `mayDeliver`, `containsForbidden`
    - `app/core/rules/MailQueue.php`: `decide` (`send`/`wait`/`drop`, 5 percobaan, interval 10 menit)
    - _Requirements: 6.10, 6.11, 6.12, 6.13, 6.14_

  - [ ]* 9.2 Tulis tes properti untuk kebijakan pengiriman email
    - **Property 20: Kebijakan pengiriman email**
    - **Validates: Requirements 6.10, 6.14**

  - [ ]* 9.3 Tulis tes properti untuk mesin status antrean
    - **Property 21: Mesin status antrean email**
    - **Validates: Requirements 6.11, 6.12**

  - [x] 9.4 Implementasi `SmtpClient`, `Mailer`, dan `bin/mail-queue.php`
    - `app/core/SmtpClient.php` (STARTTLS/SSL, AUTH LOGIN, timeout 30 detik) tanpa dependensi baru
    - `Mailer::send($to, $subject, $body, $type)`: cek `mayDeliver`, mode `log` ke `storage/mail.log`, SMTP gagal masuk `mail_queue` (alamat dan isi terenkripsi), pagar `containsForbidden`
    - Tabel `mail_queue` di `schema.sql`, `bin/mail-queue.php` memproses ulang, pada kegagalan kelima log tanpa isi, notifikasi semua admin aktif, hapus dari antrean
    - Tambah konfigurasi SMTP ke `.env.example`
    - _Requirements: 6.9, 6.10, 6.11, 6.12, 6.13, 6.14, 10.2, 10.3_

  - [ ]* 9.5 Tulis tes properti untuk teks sensitif
    - **Property 22: Teks sensitif tidak masuk email, notifikasi, atau log**
    - **Validates: Requirements 6.13, 10.2**

  - [ ]* 9.6 Tulis tes properti untuk enkripsi round-trip
    - **Property 14: Enkripsi round-trip dan kontak korban tersimpan terenkripsi**
    - **Validates: Requirements 5.9, 10.3, 10.10**

  - [ ]* 9.7 Tulis tes properti untuk deteksi perubahan ciphertext
    - **Property 15: Perubahan ciphertext terdeteksi**
    - **Validates: Requirements 10.11**

- [x] 10. Checkpoint - Merge 3 (Area A selesai)
  - Ensure all tests pass, ask the user if questions arise.

- [x] 11. Area B: statistik dan ekspor agregat (merge 4)
  - [x] 11.1 Implementasi `Aggregator` (pure)
    - `app/core/rules/Aggregator.php`: `breakdown`, `cross`, `merge` (sel di bawah 5 ke "Lainnya", sisa 1 sampai 4 digabung ke sel terkecil, total 1 sampai 4 tanpa baris), `medianHours`, `slaPercent`, `parseRange`, `csv`
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.10, 7.11, 7.12, 7.13, 7.14_

  - [ ]* 11.2 Tulis tes properti untuk penjumlahan agregat
    - **Property 23: Penjumlahan agregat statistik**
    - **Validates: Requirements 7.1, 7.11**

  - [ ]* 11.3 Tulis tes properti untuk sel kecil
    - **Property 24: Tidak ada sel kecil pada keluaran agregat**
    - **Validates: Requirements 7.3, 7.4, 7.6, 7.10, 7.13**

  - [ ]* 11.4 Tulis tes properti untuk median dan persentase SLA
    - **Property 25: Median dan persentase SLA**
    - **Validates: Requirements 7.2, 7.14**

  - [ ]* 11.5 Tulis tes properti untuk isi CSV
    - **Property 26: Isi CSV hanya agregat**
    - **Validates: Requirements 7.5**

  - [ ]* 11.6 Tulis tes properti untuk validasi rentang tanggal
    - **Property 27: Validasi rentang tanggal**
    - **Validates: Requirements 7.12**

  - [x] 11.7 Hubungkan `Stats`, endpoint, dan tampilan statistik
    - `Stats` memakai `Aggregator` (ganti `mergeSmall`), median dari `report_status_logs` ke pesan pendamping pertama, SLA dari `Sla::hours`
    - `POST /admin/statistik/ekspor` di `app/routes.php`: 403 untuk non-admin tanpa audit, CSRF, `Audit::log('stats.export')` berisi admin id, waktu, rentang
    - Tampilkan pesan rentang tidak valid, "data belum cukup", dan "belum ada data"
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.7, 7.8, 7.9, 7.12, 7.13, 7.14, 10.1, 10.5, 10.6_

- [x] 12. Area B: inklusivitas, bahasa, dan mode ringan
  - [x] 12.1 Implementasi `I18n`, `Mode`, `Emergency` dan kamus bahasa
    - `app/core/rules/I18n.php`, `Mode.php`, `Emergency.php` (pure), `app/lang/id.php` dan `app/lang/en.php`
    - `Emergency::options` selalu memuat 112 dan 129
    - _Requirements: 8.5, 8.7, 8.10, 8.12, 8.13, 8.14_

  - [ ]* 12.2 Tulis tes properti untuk terjemahan dengan cadangan
    - **Property 28: Terjemahan dengan cadangan bahasa Indonesia**
    - **Validates: Requirements 8.7**

  - [ ]* 12.3 Tulis tes properti untuk resolusi mode ringan
    - **Property 29: Resolusi mode ringan**
    - **Validates: Requirements 8.10, 8.13**

  - [ ]* 12.4 Tulis tes properti untuk opsi darurat
    - **Property 30: Opsi panggilan darurat selalu ada**
    - **Validates: Requirements 8.12, 8.14**

  - [x] 12.5 Terapkan bahasa, mode ringan, dan aksesibilitas di layout dan form
    - `POST /bahasa` dan `POST /mode` (CSRF, `RateLimiter`, simpan di `Session`), `<html lang>`, tautan "Lewati ke konten", `<main id="konten" tabindex="-1">`
    - `layouts/*`, `report/form.php`, halaman lacak: `aria-describedby` per galat, `autofocus` pada kolom tidak valid pertama, partial `partials/emergency.php`, kontras dan fokus terlihat di CSS, layout tanpa scroll horizontal pada 320px
    - Mode ringan menghilangkan `<img>` sampul, `<canvas>`, dan skrip polling, form lapor tetap berfungsi tanpa JavaScript
    - Keluar cepat: `partials/quick-exit.php` jadi ikon 44x44 dengan label muncul saat hover dan fokus, versi berlabel di form lapor, halaman kasus, dan lacak, dihapus dari layout pendamping dan admin, nama aksesibel di semua keadaan
    - `partials/public-footer.php` dan kunci `footer.credit`, `footer.not_monitored`, `emergency.note` di `app/lang/*`: hapus teks proyek praktikum, ganti kalimat `tidak dipantau 24 jam` dengan `service_limit_html()` dari `app/core/helpers.php`
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.8, 8.10, 8.11, 8.12, 8.13, 8.14, 8.15, 8.16, 8.17, 8.18, 8.19, 8.20, 10.6, 10.7, 10.8_

  - [x] 12.6 Buat `bin/check-lite-size.php`
    - Menjumlahkan HTML + CSS + JS tiap halaman dalam mode ringan dan gagal bila lebih dari 100 KB
    - _Requirements: 8.9_

- [x] 13. Area B: konten edukasi
  - [x] 13.1 Tulis `database/seed-content.sql`
    - Minimal lima artikel orisinal Bahasa Indonesia (diskriminasi di kampus/kerja, kepemimpinan perempuan, persetujuan, laki-laki sebagai sekutu, hak perempuan di tempat kerja), masing-masing minimal 400 kata, judul 10 sampai 120 karakter, ringkasan 50 sampai 300 karakter, waktu baca 1 sampai 15 menit, teks alt 5 sampai 150 karakter bila ada sampul, sumber hukum dengan penerbit, judul/nomor, dan tahun
    - `INSERT ... ON DUPLICATE KEY UPDATE` pada `slug`, `INSERT IGNORE` untuk kategori, seluruh berkas dalam `START TRANSACTION; ... COMMIT;`, `SqlFile` melakukan `rollBack` dan melaporkan galat
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8_

  - [ ]* 13.2 Tulis tes konten dan idempotensi seed
    - Periksa panjang judul, ringkasan, jumlah kata, waktu baca, teks alt, slug unik, muat dua kali tanpa galat dan jumlah baris sama, rollback saat gagal, pencarian judul tampil di bawah 2 detik
    - _Requirements: 3.2, 3.5, 3.6, 3.7, 3.8_

- [x] 14. Area B: data tim
  - [x] 14.1 Buat data tim dan hubungkan ke halaman dan README
    - `app/config/team.php` (`name`, `nim`, `modules`) sebagai satu-satunya sumber data tim; NIM Hasliza Syakina dikosongkan untuk diisi pemilik
    - `tentang.php`: hapus grid tim, avatar, dan seluruh teks yang menyebut proyek kuliah atau praktikum
    - `tentang.php`, beranda, dan form lapor: ganti kalimat `tidak dipantau 24 jam` dengan pernyataan batas layanan (bukan layanan darurat, SLA 2 jam untuk kasus bahaya dan 48 jam untuk lainnya, nomor 112 dan SAPA 129)
    - `bin/sync-team.php` menghasilkan tabel tim di `README.md`, mode `--check` keluar non-nol dan menyebut anggota yang NIM-nya kosong
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7, 9.8, 9.9_

  - [ ]* 14.2 Tulis tes properti untuk kelengkapan data tim
    - **Property 31: Data tim lengkap sebelum sinkronisasi README**
    - **Validates: Requirements 9.1, 9.8**

  - [ ]* 14.3 Tulis tes unit data tim
    - `team.php` valid (nama, 1 sampai 10 modul), halaman tentang tidak memuat nama, NIM, avatar, atau teks proyek kuliah, pernyataan batas layanan tampil di tentang, beranda, dan form lapor
    - _Requirements: 9.1, 9.3, 9.4, 9.5, 9.6_

- [x] 15. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties
- Unit tests validate specific examples and edge cases
- Requirement 10 (keamanan dan privasi) diterapkan di setiap tugas yang menambah endpoint, kolom, email, atau log
- WCAG AA penuh dan zoom 200% (Requirement 8.1, 8.4) diperiksa dengan alat audit dan daftar periksa manual, di luar otomatisasi tugas ini

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1"] },
    { "id": 1, "tasks": ["2.1", "2.3"] },
    { "id": 2, "tasks": ["2.2", "2.4", "2.5"] },
    { "id": 3, "tasks": ["3"] },
    { "id": 4, "tasks": ["4.1"] },
    { "id": 5, "tasks": ["4.2", "4.3", "4.4", "4.5", "4.6", "4.7", "4.8"] },
    { "id": 6, "tasks": ["4.9", "4.10"] },
    { "id": 7, "tasks": ["5"] },
    { "id": 8, "tasks": ["6.1", "6.2", "11.1", "12.1", "13.1", "14.1"] },
    { "id": 9, "tasks": ["6.3", "6.4", "6.5", "11.2", "11.3", "11.4", "11.5", "11.6", "11.7", "12.2", "12.3", "12.4", "13.2", "14.2", "14.3"] },
    { "id": 10, "tasks": ["6.6", "6.7", "7.1", "7.3"] },
    { "id": 11, "tasks": ["7.2", "7.4", "7.5", "7.9"] },
    { "id": 12, "tasks": ["7.6", "7.7"] },
    { "id": 13, "tasks": ["7.8", "8.1", "12.5"] },
    { "id": 14, "tasks": ["8.2", "8.3", "8.4", "12.6"] },
    { "id": 15, "tasks": ["8.5"] },
    { "id": 16, "tasks": ["9.1", "9.6", "9.7"] },
    { "id": 17, "tasks": ["9.2", "9.3", "9.4"] },
    { "id": 18, "tasks": ["9.5"] },
    { "id": 19, "tasks": ["10"] },
    { "id": 20, "tasks": ["15"] }
  ]
}
```
