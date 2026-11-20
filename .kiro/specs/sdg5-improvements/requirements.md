# Requirements Document

## Introduction

Haven adalah aplikasi web PHP vanilla MVC + MySQL untuk pelaporan, pendampingan, dan edukasi penyintas kekerasan seksual (SDG 5.2). Dokumen ini memuat perbaikan hasil review, diurutkan menurut prioritas, dan dibagi menjadi dua area kerja agar dua orang bisa mengerjakannya paralel di cabang berbeda.

### Fungsi Haven dan alur tindak lanjut laporan

Haven bukan lembaga penegak hukum. Haven menjadi pintu masuk yang aman: penyintas melapor, pendamping manusia mendampingi, lalu kasus dirujuk ke lembaga resmi bila penyintas setuju. Alur yang sudah ada di kode (`Report::STATUS`) adalah sebagai berikut.

1. **Diterima**: laporan masuk ke antrean admin, kronologi langsung terenkripsi. Penyintas mendapat kode `HV-XXXXXX` dan PIN untuk melacak.
2. **Ditinjau**: admin hanya melihat jenis kejadian, tingkat bahaya, dan wilayah. Admin tidak bisa membaca kronologi. Admin memilih pendamping yang cocok.
3. **Didampingi**: pendamping ditugaskan, membuka sesi (suara, video, tatap muka), dan berbalas pesan aman dengan penyintas.
4. **Dirujuk**: hanya jika penyintas setuju, pendamping memilih lembaga dari Direktori Layanan (UPTD PPA, Unit PPA kepolisian, rumah sakit, LBH) untuk visum, bantuan hukum, atau perlindungan.
5. **Selesai** atau **Ditutup**: pendampingan berakhir atas kesepakatan, atau admin menutup laporan dengan alasan tertulis.

Perbaikan di dokumen ini menutup celah di alur tersebut: nomor lembaga tujuan rujukan belum terisi (Area B, Requirement 1), jenis kekerasan belum lengkap (Requirement 2), korban anak dan saksi belum punya jalur sendiri (Requirement 4 dan 5), dan belum ada batas waktu balasan serta email nyata (Requirement 6).

### Pembagian area kerja

- **Area A (Pelapor dan Kasus)**: Requirement 2, 4, 5, 6. Menyentuh `Report`, form lapor, alur status, notifikasi, dan `Mailer`.
- **Area B (Konten, Layanan, dan Publik)**: Requirement 1, 3, 7, 8, 9. Menyentuh `Service`, seed konten, `Stats`, tampilan publik, README, dan halaman tentang.
- **Lintas area**: Requirement 10 berlaku untuk semua perubahan.

Titik temu yang perlu dikoordinasikan: `database/schema.sql` dan `database/seed.sql` diubah oleh kedua area (gabungkan per blok tabel). Requirement 7 bergantung pada daftar kategori baru dari Requirement 2, sehingga Area A perlu menggabungkan perubahan kategori lebih dulu.

## Glossary

- **Haven**: Aplikasi web pelaporan, pendampingan, dan edukasi penyintas kekerasan seksual.
- **Penyintas**: Pengguna yang mengalami kekerasan dan membuat laporan, anonim atau berakun.
- **Saksi**: Pengguna yang melaporkan kejadian yang dialami atau diketahui terjadi pada orang lain.
- **Pendamping**: Pengguna berperan `companion` yang mendampingi kasus.
- **Admin**: Pengguna berperan `admin` yang mengelola antrean, layanan, artikel, dan pengguna.
- **Report_System**: Modul pelaporan, termasuk model `Report`, form lapor, dan alur status.
- **Report_Categories**: Daftar jenis kejadian dalam `Report::CATEGORIES` dan `Report::CATEGORY_DESC`, serta kolom `reports.category`.
- **Service_Directory**: Direktori layanan (model `Service`, halaman publik, dan Admin > Layanan).
- **Verified_Service**: Layanan dengan `is_verified` bernilai 1 dan memiliki `verified_at`.
- **Unverified_Service**: Layanan dengan `is_verified` bernilai 0.
- **Education_Module**: Modul artikel edukasi dan seed konten (`database/seed-content.sql`).
- **Stats_Module**: Model `Stats`, endpoint JSON statistik, dan dashboard.
- **Aggregate_Export**: Berkas hasil ekspor data agregat anonim dari Stats_Module.
- **Small_Cell**: Kategori atau kombinasi kategori yang jumlah kasusnya kurang dari 5.
- **Child_Case**: Laporan yang ditandai korbannya berusia di bawah 18 tahun.
- **Child_Referral_Panel**: Panel rujukan yang menampilkan UPTD PPA anak, KPAI, dan SAPA 129.
- **In_Danger_Case**: Laporan dengan `in_danger` bernilai 1.
- **Reply_SLA**: Batas waktu maksimum antara laporan masuk atau pesan penyintas dan balasan pertama dari pihak Haven.
- **Reminder_Service**: Komponen terjadwal yang membuat pengingat untuk kasus yang melewati Reply_SLA.
- **Mailer**: Komponen `Mailer` yang mengirim email.
- **Mail_Queue**: Antrean email yang menyimpan email yang belum terkirim untuk dicoba ulang.
- **Audit_Log**: Catatan `audit_logs` yang merekam akses dan perubahan data sensitif.
- **Chronology**: Isi kronologi laporan (`chronology_enc`) dan kontak (`contact_enc`) yang terenkripsi.
- **Lite_Mode**: Mode tampilan ringan untuk koneksi lemah.
- **Team_Info**: Data nama, NIM, dan peran anggota tim di README dan halaman tentang.

## Requirements

### Requirement 1: Penanda verifikasi dan penyembunyian nomor layanan (Area B, prioritas 1)

**User Story:** As a penyintas, I want hanya nomor layanan yang sudah dicek yang tampil, so that aku tidak menghubungi nomor yang salah saat membutuhkan pertolongan.

#### Acceptance Criteria

1. WHILE a service is an Unverified_Service, THE Service_Directory SHALL omit its phone number and WhatsApp number from every public page and every public JSON response, and SHALL return null for both fields in the JSON response.
2. WHILE a service is an Unverified_Service, THE Service_Directory SHALL display the exact label "Perlu verifikasi" on its public listing in place of the phone number and WhatsApp number.
3. WHEN an Admin marks a service as verified, THE Service_Directory SHALL require at least one of phone number or WhatsApp number to be non-empty after trimming whitespace, and a `verified_at` date in the format YYYY-MM-DD that is not later than the current server date.
4. IF an Admin marks a service as verified without a phone number and without a WhatsApp number, THEN THE Service_Directory SHALL reject the save, keep the previously stored service data unchanged, and show an error message in Bahasa Indonesia stating that a contact number is required.
5. IF an Admin marks a service as verified with a `verified_at` date later than the current server date or in an invalid format, THEN THE Service_Directory SHALL reject the save, keep the previously stored service data unchanged, and show an error message in Bahasa Indonesia stating that the verification date is invalid.
6. WHEN an Admin changes the phone number or WhatsApp number of a Verified_Service, THE Service_Directory SHALL set the service to Unverified_Service in the same save operation, and it SHALL remain Unverified_Service until the Admin marks it as verified again.
7. WHEN a Verified_Service has a `verified_at` date more than 180 days before the current server date, THE Service_Directory SHALL show the service in the Admin list with the label "Perlu dicek ulang" and SHALL continue to show its contact numbers publicly.
8. WHEN a Verified_Service is displayed publicly, THE Service_Directory SHALL show the `verified_at` date in the format DD-MM-YYYY adjacent to the phone number or WhatsApp number.
9. THE Service_Directory SHALL show in the Admin service list the count of Unverified_Service entries for each type (`uptd`, `polisi`, `rs`, `lbh`), displaying 0 for a type with no Unverified_Service entries.
10. WHEN an Admin saves a service, THE Audit_Log SHALL record one entry containing the service id, the previous verification status, and the new verification status.
11. FOR ALL services, THE Service_Directory SHALL return a public representation in which phone and WhatsApp are null if and only if the service is an Unverified_Service (invariant).

### Requirement 2: Perluasan kategori laporan (Area A, prioritas 2)

**User Story:** As a penyintas, I want memilih jenis kekerasan yang sesuai pengalamanku, so that laporanku diarahkan ke pendamping dan lembaga yang tepat.

#### Acceptance Criteria

1. THE Report_Categories SHALL include the six new categories KDRT, kekerasan dalam pacaran, kekerasan psikis, kekerasan ekonomi, perkawinan anak, and perdagangan orang, in addition to all categories already present in `Report::CATEGORIES` before this change.
2. THE Report_Categories SHALL define for every category a unique slug of 1 to 40 characters (lowercase letters, digits, hyphens, and underscores only), a label of 1 to 60 characters, and a description of exactly one sentence of 1 to 200 characters, with label and description written in Bahasa Indonesia.
3. THE Report_System SHALL store the category in a `reports.category` column whose allowed values equal the set of keys of `Report::CATEGORIES`.
4. WHEN the database schema is applied to a database containing existing reports, THE Report_System SHALL keep the number of reports and the `category` value of each existing report identical to their values before the schema was applied.
5. WHEN a survivor opens the report form, THE Report_System SHALL display every category in `Report::CATEGORIES` as a selectable option together with its label and description.
6. IF a submitted category is empty or is not a key of `Report::CATEGORIES`, THEN THE Report_System SHALL reject the submission, store no report, redisplay the report form with an error message indicating the category is invalid, and retain the other values the survivor entered.
7. WHEN an Admin opens the case queue, THE Report_System SHALL offer a category filter containing one option per category in `Report::CATEGORIES` plus an option for all categories.
8. WHEN an Admin selects a category in the case queue filter, THE Report_System SHALL list only reports whose category equals the selected category.
9. WHEN a report with category perkawinan anak is submitted, THE Report_System SHALL mark the report as a Child_Case at the moment it is stored.
10. WHEN a report with category perdagangan orang is submitted, THE Report_System SHALL set the report urgency to "tinggi" at the moment it is stored.
11. FOR ALL categories in `Report::CATEGORIES`, THE Report_System SHALL have a matching key in `Report::CATEGORY_DESC` and a matching allowed value in the `reports.category` column (invariant).

### Requirement 3: Konten edukasi kesetaraan gender (Area B, prioritas 3)

**User Story:** As a pembaca, I want artikel tentang kesetaraan gender, so that aku memahami hak, persetujuan, dan peran semua pihak dalam mencegah kekerasan.

#### Acceptance Criteria

1. THE Education_Module SHALL provide at least five new articles with status published in `database/seed-content.sql`, with at least one article for each of these topics: diskriminasi terhadap perempuan di kampus dan tempat kerja, partisipasi dan kepemimpinan perempuan, persetujuan (consent), laki-laki sebagai sekutu, dan hak perempuan di tempat kerja.
2. THE Education_Module SHALL write each new article in original Bahasa Indonesia text with a title of 10 to 120 characters, an excerpt of 50 to 300 characters, a body of at least 400 words, a category, and an estimated reading time of 1 to 15 minutes.
3. THE Education_Module SHALL assign each new article to exactly one article category, and that category SHALL exist in the article category list after the seed content is loaded.
4. WHERE an article cites a law, regulation, or statistic, THE Education_Module SHALL state in the article text the name of the issuing body or publisher, the title or number of the law or report, and a four-digit publication year.
5. WHEN the seed content is loaded twice in a row on the same database, THE Education_Module SHALL complete the second load without error, and the number of articles and categories SHALL be identical after both loads, with every article slug unique.
6. WHERE an article has a cover image, THE Education_Module SHALL provide alternative text of 5 to 150 characters describing that image.
7. WHEN a visitor opens the education index, THE Education_Module SHALL list each new article under its category and SHALL include it in search results for any word in its title, with results displayed within 2 seconds.
8. IF loading `database/seed-content.sql` fails with an error, THEN THE Education_Module SHALL report the error to the person running the load and SHALL leave previously existing articles unchanged.

### Requirement 4: Alur khusus korban anak (Area A, prioritas 4)

**User Story:** As a penyintas anak atau orang yang mendampinginya, I want jalur rujukan khusus anak, so that anak segera dihubungkan ke lembaga perlindungan anak.

#### Acceptance Criteria

1. WHEN a survivor opens the report form, THE Report_System SHALL display a required question asking whether the victim is under 18 years old, with exactly three options: "ya", "tidak", and "tidak yakin".
2. IF a survivor submits the report form without selecting an option for the age question, THEN THE Report_System SHALL reject the submission, display an error message indicating that the age question is required, and retain all other data entered in the form.
3. WHEN a report is submitted with the answer "ya" or "tidak yakin", THE Report_System SHALL mark the report as a Child_Case. WHEN a report is submitted with the answer "tidak", THE Report_System SHALL NOT mark it as a Child_Case.
4. WHEN the sent-confirmation page or the tracking page is displayed for a Child_Case, THE Report_System SHALL show the Child_Referral_Panel on that page.
5. THE Child_Referral_Panel SHALL list exactly three services, UPTD PPA anak, KPAI, and SAPA 129. Each service shall appear as its own entry showing the service name and contact, using only Verified_Service data.
6. IF a service required in the Child_Referral_Panel is an Unverified_Service, THEN THE Child_Referral_Panel SHALL display that service's name with the label "Perlu verifikasi", omit its contact, and still display the other required services.
7. WHEN a Child_Case is created, THE Report_System SHALL set the urgency to "tinggi" regardless of the answers to other form questions.
8. WHEN an Admin opens the case queue, THE Report_System SHALL show an "Anak" badge on every Child_Case entry and no other entry. When the Admin applies the Child_Case filter, THE Report_System SHALL list only Child_Case entries.
9. WHEN an Admin assigns a companion to a Child_Case, THE Report_System SHALL offer only companions marked as trained for child cases in the region recorded on the report.
10. IF no companion is marked as trained for child cases in the region recorded on a Child_Case, THEN THE Report_System SHALL show the Admin a warning that no trained companion is available. The system SHALL then list all companions with companions from the region recorded on the report shown first, and complete the assignment only after the Admin explicitly confirms it.
11. WHEN a Child_Case is created, THE Audit_Log SHALL record an entry containing the report identifier, the event type "Child_Case created", and the creation timestamp, and SHALL NOT record the Chronology.

### Requirement 5: Pelaporan oleh saksi atau pihak ketiga (Area A, prioritas 5)

**User Story:** As a saksi, I want melapor atas nama orang lain, so that korban yang belum berani melapor tetap bisa dibantu.

#### Acceptance Criteria

1. WHEN a visitor opens the report form, THE Report_System SHALL display a required choice of exactly one reporter role, "korban" or "saksi", and SHALL block submission until one role is selected.
2. WHEN the reporter selects "saksi" and submits the form, THE Report_System SHALL store the report with reporter type "saksi".
3. WHEN a witness report is submitted successfully, THE Report_System SHALL issue a report code and a 6-digit numeric PIN using the same format, uniqueness and PIN rules as a survivor report.
4. WHERE the witness chooses anonymous mode, THE Report_System SHALL store the witness report with an empty user id.
5. WHEN a witness report is submitted successfully, THE Report_System SHALL show, on the confirmation page that displays the report code and PIN, a notice that Haven cannot contact the victim without the victim's consent.
6. WHEN a witness report is displayed to a Pendamping in the case queue or the case detail page, THE Report_System SHALL show the label "Dilaporkan oleh saksi" on that report.
7. WHEN a Pendamping contacts the victim of a witness report, THE Report_System SHALL let the Pendamping record that the victim consented to follow-up, and SHALL store the consent with the recording Pendamping's id and a timestamp.
8. IF a Pendamping changes the status of a witness report to "dirujuk" from any current status and no victim consent has been recorded, THEN THE Report_System SHALL reject the change, keep the current status, and show an error indicating that victim consent is required.
9. IF a witness enters contact details of the victim (maximum 255 characters), THEN THE Report_System SHALL store those details encrypted with AES-256-GCM, the same mechanism used for other contact data, and SHALL NOT store them in plaintext.
10. WHEN a witness report is submitted, THE Report_System SHALL validate the CSRF token and apply the same rate-limit threshold and time window as a survivor report.
11. IF a witness report submission has an invalid CSRF token or exceeds the rate limit, THEN THE Report_System SHALL reject the submission, create no report, and show an error indicating the reason, with the entered form data preserved except the PIN.

### Requirement 6: SLA balasan, pengingat kasus mendesak, dan email nyata (Area A, prioritas 6)

**User Story:** As a penyintas, I want kepastian kapan laporanku dibalas dan kasus bahaya tidak terlewat, so that aku tidak menunggu tanpa kejelasan.

#### Acceptance Criteria

1. THE Report_System SHALL define a Reply_SLA of 2 hours for an In_Danger_Case and 48 hours for other cases, stored as configurable positive-integer hour values in `.env`, and SHALL use these defaults when a value is absent or is not a positive integer.
2. WHEN a report is submitted, THE Report_System SHALL show the applicable Reply_SLA, in hours, on the sent-confirmation page and on the tracking page for that case.
3. WHEN the Reminder_Service runs and finds an In_Danger_Case that has remained in status "diterima" for more than its Reply_SLA since submission, THE Reminder_Service SHALL create one in-app notification for every active Admin.
4. WHEN the Reminder_Service runs and finds a case with no companion reply for more than its Reply_SLA since the latest survivor message, THE Reminder_Service SHALL create one in-app notification for the assigned Pendamping and for every active Admin, or for every active Admin only if no Pendamping is assigned.
5. THE Reminder_Service SHALL create at most one reminder per case per reminder type (criterion 3 or 4) per SLA period, where an SLA period is a consecutive interval of Reply_SLA hours beginning when the case first exceeds its Reply_SLA.
6. WHEN the Reminder_Service runs two or more times within the same 60 seconds, THE Reminder_Service SHALL create no reminders beyond those created by the first run (idempotence).
7. THE Reminder_Service SHALL run from a command-line script in `bin/` that can be scheduled with Windows Task Scheduler or cron, and SHALL exit with code 0 when a run completes without error.
8. WHEN the case queue is displayed, THE Report_System SHALL show a "Lewat SLA" badge on each case that exceeds its Reply_SLA and SHALL show no such badge on any other case.
9. THE Mailer SHALL send email through SMTP using host, port, user, password, and encryption read from `.env`, and SHALL treat an SMTP connection or send that takes longer than 30 seconds as a failed send.
10. IF the SMTP host or port is absent or empty in `.env`, THEN THE Mailer SHALL write the email to `storage/mail.log` as it does now and SHALL NOT attempt an SMTP connection.
11. IF an SMTP send fails, THEN THE Mailer SHALL store the email in the Mail_Queue and retry up to 5 times at 10-minute intervals, and SHALL keep the email in the Mail_Queue until it is sent or the fifth retry fails.
12. IF the fifth retry for an email fails, THEN THE Mailer SHALL log the failure with timestamp, recipient address, and email type but without the email body, SHALL create an in-app notification for every active Admin, and SHALL remove the email from the Mail_Queue.
13. THE Mailer SHALL exclude the Chronology, the PIN, and the report code from the subject and body of every email.
14. WHILE a user has `allow_email` set to 0, THE Mailer SHALL send that user only password-reset and invitation emails and SHALL NOT queue or send any other email to that user.
15. WHEN a reminder or notification is due for an anonymous survivor who has no email address, THE Reminder_Service SHALL deliver it through an in-app notification only and SHALL NOT create an email for it.
16. IF the Reminder_Service cannot complete a run because the database is unavailable, THEN THE Reminder_Service SHALL exit with a non-zero code, SHALL write a failure entry to the application log, and SHALL create no reminders in that run.

### Requirement 7: Statistik tambahan dan ekspor agregat anonim (Area B, prioritas 7)

**User Story:** As a admin, I want statistik yang lebih lengkap dan ekspor data agregat, so that Haven bisa melaporkan dampaknya tanpa membuka identitas siapa pun.

#### Acceptance Criteria

1. WHEN an Admin selects a date range, THE Stats_Module SHALL report the number of reports created within that range, grouped separately by category (including the categories from Requirement 2), by province, by calendar month, and by status.
2. WHEN an Admin selects a date range, THE Stats_Module SHALL report the median time, in whole hours, from "diterima" to the first companion reply, and the percentage of cases that met the Reply_SLA, rounded to one decimal place.
3. WHEN a category count is below 5, THE Stats_Module SHALL merge that category into an "Lainnya" group in every public and Admin statistic.
4. WHEN a combination of two dimensions (for example category and province) has a count below 5, THE Stats_Module SHALL merge that combination into an "Lainnya" group.
5. WHEN an Admin requests an Aggregate_Export, THE Stats_Module SHALL produce a CSV file containing only aggregate counts and no report code, user id, free text, or timestamp finer than one month.
6. THE Aggregate_Export SHALL apply the same Small_Cell merging as the on-screen statistics.
7. WHEN an Admin requests an Aggregate_Export, THE Audit_Log SHALL record the Admin id, the request time, and the selected date range.
8. IF a user who is not an Admin requests an Aggregate_Export, THEN THE Stats_Module SHALL return an HTTP 403 response, create no CSV file, and write no Audit_Log entry for an export.
9. IF the request that creates an Aggregate_Export has a missing or invalid CSRF token, THEN THE Stats_Module SHALL reject the request, create no CSV file, and display an error message indicating the request could not be verified.
10. FOR ALL aggregate outputs, THE Stats_Module SHALL produce no row with a count between 1 and 4 (invariant).
11. FOR ALL aggregate outputs where the total number of reports in the selected range is 0 or at least 5, THE Stats_Module SHALL produce group totals equal to that total (invariant).
12. IF the selected date range has a start date after its end date, or an unparseable date, THEN THE Stats_Module SHALL display no statistics, produce no Aggregate_Export, and display an error message indicating the range is invalid.
13. IF the total number of reports in the selected range is between 1 and 4, THEN THE Stats_Module SHALL display no breakdown rows and SHALL display a message indicating the data is insufficient to be shown.
14. IF no case in the selected range has a companion reply, THEN THE Stats_Module SHALL display the median reply time as "belum ada data" instead of a number.

### Requirement 8: Inklusivitas, bahasa lain, dan mode ringan (Area B, prioritas 8)

**User Story:** As a pengguna dengan disabilitas, penutur bahasa lain, atau sinyal lemah, I want Haven yang bisa kupakai, so that aku tidak terhalang saat membutuhkan bantuan.

#### Acceptance Criteria

1. THE Haven SHALL meet WCAG 2.1 level AA on the public pages, the report form, and the tracking page, including a color contrast ratio of at least 4.5:1 for normal text and 3:1 for large text and interface components, operation of every interactive element by keyboard alone, a visible focus indicator on every focused element, and a text alternative for every non-decorative image.
2. THE Haven SHALL provide a "Lewati ke konten" link as the first focusable element on every page, and WHEN the user activates the link, THE Haven SHALL move keyboard focus to the main content region.
3. IF a form validation error occurs, THEN THE Haven SHALL associate each error message with its field using `aria-describedby`, move focus to the first invalid field, and keep the values the user already entered in the other fields.
4. THE Haven SHALL let the user enlarge text up to 200% on the public pages, the report form, and the tracking page without clipped or overlapping content, without loss of any function, and without horizontal scrolling at a viewport width of 320 CSS pixels.
5. THE Haven SHALL provide every static user interface string of the public pages, the report form, and the tracking page in English in addition to Bahasa Indonesia, excluding content written by users, companions, and admins.
6. WHEN a user selects a language, THE Haven SHALL store the choice in the session, apply it to every later page in that session, and set the page language attribute to the selected language.
7. IF a string has no English translation, THEN THE Haven SHALL show the Bahasa Indonesia string for that string.
8. WHERE the user enables Lite_Mode, THE Haven SHALL omit cover images, canvas charts, and auto-refresh polling requests from the public pages, the report form, and the tracking page.
9. WHERE the user enables Lite_Mode, THE Haven SHALL load each of the public pages, the report form, and the tracking page with no more than 100 KB of transferred data on a first visit with an empty browser cache, excluding user-uploaded files.
10. WHEN a request carries a `Save-Data: on` header and the user has not chosen a mode in the current session, THE Haven SHALL start in Lite_Mode.
11. WHILE Lite_Mode is active, THE Haven SHALL let the user complete and submit the report form with JavaScript disabled, and THE Haven SHALL show the same confirmation page as with JavaScript enabled.
12. THE Haven SHALL show the emergency call options (112 and SAPA 129 from Verified_Service entries) on the report form and the tracking page in every language and in both modes, without requiring JavaScript or any user interaction to reveal them.
13. WHEN a user switches Lite_Mode on or off, THE Haven SHALL store the choice in the session, apply it to every later page in that session, and give the user's choice precedence over the `Save-Data` header.
14. IF no Verified_Service entry exists for 112 or SAPA 129, THEN THE Haven SHALL still show the numbers 112 and 129 as the emergency call options on the report form and the tracking page.
15. THE Haven SHALL show a quick-exit control on every public page and on every survivor page, and SHALL NOT show it on any companion page or admin page.
16. WHEN the user activates the quick-exit control, or presses Escape twice within 1 second, THE Haven SHALL replace the current page with a neutral external page so that the browser Back button does not return to Haven.
17. WHERE the page is the report form, a case page, or the tracking page, THE Haven SHALL show the quick-exit control with a visible text label.
18. WHERE the page is a public page other than those in criterion 17, THE Haven SHALL show the quick-exit control as an icon-only control of at least 44 by 44 CSS pixels that reveals its text label on pointer hover and on keyboard focus.
19. THE Haven SHALL give the quick-exit control an accessible name in every state, including the icon-only state, and SHALL keep it reachable by keyboard.
20. THE Haven SHALL keep the quick-exit control operable without JavaScript for the control itself, or SHALL hide it when its action cannot run.

### Requirement 9: Kredit akademik dan batas layanan (Area B, prioritas 9)

**User Story:** As a dosen penilai, I want melihat siapa yang membuat Haven beserta modulnya di README, so that penilaian jelas penanggung jawabnya, tanpa menampilkan data mahasiswa di halaman publik.

#### Acceptance Criteria

1. THE Team_Info SHALL list for every team member the full name (1 to 100 characters), the NIM (1 to 20 characters), and the modules they built (1 to 10 modules per member, each named after an existing Haven module).
2. THE Haven SHALL keep the Team_Info in one separate PHP data file `app/config/team.php`.
3. THE Haven SHALL show no team member name, NIM, initial, avatar, or photo on any public page.
4. THE Haven SHALL remove from the about page the team grid, the member avatars, and every text that identifies Haven as a course assignment or a student project.
5. WHEN the about page is displayed, THE Haven SHALL state the service limits: that Haven is not an emergency service, the applicable Reply_SLA in hours for ordinary cases and for an In_Danger_Case, and the emergency numbers 112 and SAPA 129.
6. THE Haven SHALL show on the home page, the report form, and the about page the service-limit statement from criterion 5 in place of any wording that only says reports are not monitored 24 hours.
7. THE Haven SHALL show the Team_Info in `README.md` as a table generated from `app/config/team.php`.
8. IF a member in `app/config/team.php` has an empty NIM, THEN `bin/sync-team.php --check` SHALL exit with a non-zero code and name every member whose NIM is missing.
9. WHEN the team data is changed in `app/config/team.php`, THE Haven SHALL require no edit to any view file for the change to appear in `README.md` after `bin/sync-team.php` runs.
### Requirement 10: Batasan keamanan dan privasi (lintas area)

**User Story:** As a penyintas, I want perlindungan data tetap utuh setelah semua perbaikan, so that perubahan baru tidak membuka identitas atau ceritaku.

#### Acceptance Criteria

1. IF a user with the Admin role requests a view or export introduced by this feature that contains the Chronology, THEN THE Haven SHALL reject the request with an HTTP 403 response and return no Chronology content.
2. THE Haven SHALL exclude the Chronology text from every notification, email, and log entry introduced by this feature, for all recipients including Admin.
3. THE Haven SHALL store the Chronology, contact data, and any new sensitive field in this feature with AES-256-GCM encryption, so that no database column holds these values in plaintext.
4. WHERE a report is anonymous, THE Haven SHALL store no user id, email, or IP address linked to that report in any new table or column.
5. WHEN a user reads or exports report data through a new feature, THE Audit_Log SHALL record the actor (user id and role), the action, the target report, and the timestamp, within the same request.
6. WHEN a new state-changing endpoint receives a request, THE Haven SHALL validate the CSRF token before processing the request.
7. WHEN a new public or authentication-related endpoint receives a request, THE Haven SHALL apply rate limiting through `RateLimiter`.
8. IF a request exceeds the `RateLimiter` limit on a new public or authentication-related endpoint, THEN THE Haven SHALL reject the request with a rate-limit error response, show the user an error indicating that too many attempts were made, and make no change to data.
9. IF a CSRF token is missing or invalid, THEN THE Haven SHALL reject the request with an HTTP 419 or 403 response and make no change to data.
10. FOR ALL string values of 0 to 10,000 UTF-8 characters encrypted by the Crypto component, decrypting the encrypted value SHALL return the original value (round-trip property).
11. IF the Crypto component decrypts a value whose ciphertext or authentication tag was altered, THEN THE Crypto component SHALL fail with an error and return no plaintext.
12. WHEN schema changes are made, THE Haven SHALL provide the change in `database/schema.sql` so that running `bin/db-reset.php` on an empty database finishes without error and recreates every table and column used by this feature.