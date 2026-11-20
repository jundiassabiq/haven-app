# app/core/rules

Logika murni fitur **sdg5-improvements**: fungsi statis tanpa akses database,
tanpa superglobal, dan tanpa `time()`. Waktu selalu masuk sebagai parameter
`DateTimeImmutable`. Controller dan model hanya memanggil kelas di sini.

Pemisahan ini membuat tes properti bisa jalan tanpa MySQL.

Konvensi:

- Satu kelas per berkas, nama kelas sama dengan nama berkas (`Sla.php` -> `Sla`),
  namespace global seperti sisa `app/core`. Autoload lewat `app/bootstrap.php`.
- Semua metode `public static`, tanpa state.
- Pesan galat yang dilihat pengguna ditulis dalam Bahasa Indonesia.

Rencana berkas (lihat `.kiro/specs/sdg5-improvements/design.md`):
`Sla.php`, `ServiceRules.php`, `ReportIntake.php`, `ConsentGuard.php`,
`Assignment.php`, `ChildReferral.php`, `ReminderPlanner.php`, `MailPolicy.php`,
`MailQueue.php`, `Aggregator.php`, `I18n.php`, `Mode.php`, `Emergency.php`.
