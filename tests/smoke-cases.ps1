# End-to-end test for Tasks 4–10: report → track → triage → assign → messages → schedule → referral → close.
# Needs a running server and a freshly reset database:
#   C:\xampp\php\php.exe bin\db-reset.php
#   powershell -ExecutionPolicy Bypass -File tests\smoke-cases.ps1
param([string]$B = "http://127.0.0.1:8000")
. "$PSScriptRoot\smoke-lib.ps1"

# Fixtures
$png = "$env:TEMP\haven-bukti.png"; [IO.File]::WriteAllBytes($png, [Convert]::FromBase64String("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=="))
$pdf = "$env:TEMP\haven-bukti.pdf"; [IO.File]::WriteAllText($pdf, "%PDF-1.4`n1 0 obj`n<<>>`nendobj`ntrailer`n<<>>`n%%EOF`n")
$fake = "$env:TEMP\haven-palsu.png"; [IO.File]::WriteAllText($fake, '<?php echo "x";')
$story = "Seorang kakak tingkat berulang kali mengirim pesan bernuansa seksual setelah saya menolak. RAHASIA-UJI-123."
$base = @{ jenis = "nonfisik"; bahaya = "tidak"; kronologi = $story; berlangsung = "ya"; tanggal = (Get-Date).AddDays(-3).ToString("yyyy-MM-dd"); tempat = "Kampus atau sekolah"; provinsi = "Jawa Tengah"; kota = "Banyumas"; pelaku = "Teman kuliah atau rekan kerja"; identitas = "anonim"; kontak_email = "1"; email = "aman@contoh.id"; setuju = "1" }

"Modul 2: Pelaporan"
$r = Req anon "/lapor"; Check "form lapor 200" ($r.code -eq 200 -and (Has $r "Apa yang kamu alami?"))
$bad = $base.Clone(); $bad.kronologi = "pendek"; $bad.setuju = ""
$r = Post anon "/lapor" "/lapor" $bad; Check "validasi server: kronologi pendek & persetujuan" ((Has $r "minimal 30 karakter") -and (Has $r "Centang ini agar kami"))
Check "isian lama tetap terisi" (Has $r 'value="Banyumas"')
$r = Post anon "/lapor" "/lapor" $base.Clone() @{ "bukti[]" = @("$png;type=image/png", "$fake;type=image/png") }; Check "file palsu (skrip berekstensi .png) ditolak" (Has $r "bukan JPG, PNG, atau PDF")
$r = Post anon "/lapor" "/lapor" $base.Clone() @{ "bukti[]" = @("$png;type=image/png", "$pdf;type=application/pdf") }
$code = [regex]::Match($r.body, '<dd>(HV-[A-Z0-9]{6})</dd>').Groups[1].Value; $pin = [regex]::Match($r.body, '<dd>([0-9]{6})</dd>').Groups[1].Value
Check "laporan terkirim, kode & PIN tampil" ($r.url -eq "/lapor/terkirim" -and $code -and $pin) "$($r.url)"
Check "kode & PIN hanya tampil sekali" ((Req anon "/lapor/terkirim").url -eq "/lacak")
$rid = Sql "SELECT id FROM reports WHERE code='$code'"
Check "kronologi tersimpan terenkripsi" ((Sql "SELECT chronology_enc LIKE '%RAHASIA%' FROM reports WHERE id=$rid") -eq "0" -and (Sql "SELECT LENGTH(chronology_enc) > 100 FROM reports WHERE id=$rid") -eq "1")
Check "kontak terenkripsi" ((Sql "SELECT contact_enc LIKE '%aman@contoh%' FROM reports WHERE id=$rid") -eq "0")
Check "2 bukti tersimpan, nama asli tidak terbaca" ((Sql "SELECT COUNT(*) FROM report_attachments WHERE report_id=$rid AND original_name_enc NOT LIKE '%haven-bukti%'") -eq "2")
$stored = Sql "SELECT stored_name FROM report_attachments WHERE report_id=$rid LIMIT 1"
Check "file bukti ada di storage/uploads (di luar public)" (Test-Path "$PSScriptRoot\..\storage\uploads\$stored")
Check "PIN disimpan sebagai hash" ((Sql "SELECT pin_hash LIKE '`$2y`$%' AND pin_hash NOT LIKE '%$pin%' FROM reports WHERE id=$rid") -eq "1")
Check "urgensi awal sedang (masih berlangsung)" ((Sql "SELECT urgency FROM reports WHERE id=$rid") -eq "sedang")
$r = Req anon "/kasus/$code"; Check "pelapor langsung bisa membuka laporannya" ($r.code -eq 200 -and (Has $r "Diterima"))

"Modul 3: Lacak"
Check "browser lain tanpa PIN diarahkan ke /lacak" ((Req x "/kasus/$code").url -eq "/lacak")
$r = Post x "/lacak" "/lacak" @{ kode = $code; pin = "000000" }; Check "PIN salah ditolak" (Has $r "tidak cocok")
$r = Post x "/lacak" "/lacak" @{ kode = $code.ToLower().Replace("-", ""); pin = $pin }; Check "kode huruf kecil tanpa strip tetap diterima" ($r.url -eq "/kasus/$code")
$m = Api x "/kasus/$code" "/api/kasus/$code/pesan"; Check "API pesan: kosong sebelum ditugaskan" ($m.code -eq 200 -and $m.json.messages.Count -eq 0)
$m = Api x "/kasus/$code" "/api/kasus/$code/pesan" @{ body = "halo" }; Check "kirim pesan sebelum ada pendamping = 409" ($m.code -eq 409)
Check "tamu tanpa sesi tidak bisa baca pesan (403)" ((Api z "/" "/api/kasus/$code/pesan").code -eq 403)

"Modul 4: Manajemen kasus"
$null = Login adm "admin@demo.id"
$r = Req adm "/admin/antrean"; Check "laporan muncul di antrean" (Has $r $code)
$n = Api adm "/admin" "/api/notifikasi"; Check "admin dapat notifikasi laporan baru" ($n.json.unread -ge 1 -and ($n.json.items.title -join "|") -like "*$code*")
$r = Req adm "/admin/kasus/$code"; Check "admin melihat data pemilahan, tanpa kronologi/kontak" ((Has $r "Data untuk pemilahan") -and -not (Has $r "RAHASIA-UJI") -and -not (Has $r "aman@contoh"))
$r = Req adm "/admin/antrean?status=diterima&jenis=nonfisik&q=Banyumas"; Check "filter antrean" (Has $r $code)
$r = Req adm "/admin/antrean?status=selesai"; Check "filter status lain tidak menampilkan" (-not (Has $r $code))
$r = Post adm "/admin/kasus/$code" "/admin/kasus/$code/tugaskan" @{ urgensi = "tinggi"; pendamping = "3" }; Check "tidak bisa menugaskan akun non-pendamping" (Has $r "Pilih pendamping aktif")
$r = Post adm "/admin/kasus/$code" "/admin/kasus/$code/tugaskan" @{ urgensi = "tinggi"; pendamping = "2"; catatan = "Sapa dalam 1 jam." }; Check "ditugaskan ke Dewi" ((Flash $r) -like "*ditugaskan ke Dewi*")
Check "status didampingi, 3 log status" ((Sql "SELECT CONCAT(status,'/',urgency,'/',assigned_to,'/',(SELECT COUNT(*) FROM report_status_logs WHERE report_id=$rid)) FROM reports WHERE id=$rid") -eq "didampingi/tinggi/2/3")

$null = Login dewi "dewi@demo.id"
$r = Req dewi "/pendamping/kasus"; Check "kasus tampil di Kasus saya" (Has $r $code)
$r = Req dewi "/pendamping/kasus/$code"; Check "pendamping membaca kronologi & kontak terdekripsi" ((Has $r "RAHASIA-UJI-123") -and (Has $r "aman@contoh.id"))
Check "catatan admin terlihat di catatan internal" (Has $r "Sapa dalam 1 jam.")
$n = Api dewi "/pendamping" "/api/notifikasi"; Check "pendamping dapat notifikasi penugasan" (($n.json.items.title -join "|") -like "*Kasus baru ditugaskan: $code*")
$null = Login sari "sari@demo.id"
Check "pendamping lain = 403" ((Req sari "/pendamping/kasus/$code").code -eq 403)
Check "akses ditolak tercatat di audit" ([int](Sql "SELECT COUNT(*) FROM audit_logs WHERE action='access.denied' AND object_ref='$code'") -ge 1)
$att = Sql "SELECT id FROM report_attachments WHERE report_id=$rid ORDER BY id LIMIT 1"
$h = (curl.exe -s -o NUL -w "%{http_code} %{content_type}" -b (Jar dewi) "$B/lampiran/$att"); Check "pendamping membuka bukti (image/png)" ($h -eq "200 image/png") $h
Check "penyintas (sesi kode+PIN) membuka bukti" ((curl.exe -s -o NUL -w "%{http_code}" -b (Jar x) "$B/lampiran/$att") -eq "200")
Check "pendamping lain tidak bisa membuka bukti" ((curl.exe -s -o NUL -w "%{http_code}" -b (Jar sari) "$B/lampiran/$att") -eq "403")
Check "tamu tidak bisa membuka bukti" ((curl.exe -s -o NUL -w "%{http_code}" "$B/lampiran/$att") -eq "403")
$r = Post dewi "/pendamping/kasus/$code" "/pendamping/kasus/$code/catatan" @{ title = "Kontak pertama"; body = "Penyintas aman. CATATAN-UJI." }; Check "catatan internal tersimpan" ((Flash $r) -like "Catatan disimpan*" -and (Has $r "CATATAN-UJI"))
Check "catatan terenkripsi di DB" ((Sql "SELECT COUNT(*) FROM case_notes WHERE body_enc LIKE '%CATATAN-UJI%'") -eq "0")

"Modul 6: Pesan aman"
$m = Api dewi "/pendamping/kasus/$code" "/api/kasus/$code/pesan" @{ body = "Halo, aku Dewi. Kamu aman sekarang?" }; Check "pendamping mengirim pesan" ($m.code -eq 201)
$m = Api x "/kasus/$code" "/api/kasus/$code/pesan"
Check "penyintas menerima pesan + pesan sistem" ($m.json.messages.Count -eq 2 -and $m.json.messages[0].system -and $m.json.messages[1].sender -eq "Dewi A." -and -not $m.json.messages[1].mine)
$last = $m.json.messages[-1].id
$m = Api x "/kasus/$code" "/api/kasus/$code/pesan" @{ body = "Iya, aku aman." }; Check "penyintas membalas" ($m.code -eq 201)
$m = Api anon "/kasus/$code" "/api/kasus/$code/pesan" @{ body = "Terima kasih." }; Check "balasan kedua" ($m.code -eq 201)
$m = Api x "/kasus/$code" "/api/kasus/$code/pesan?after=$last"; Check "polling ?after hanya mengembalikan pesan baru" ($m.json.messages.Count -eq 2 -and $m.json.messages[0].mine)
$m = Api dewi "/pendamping/kasus/$code" "/api/kasus/$code/pesan"; Check "pendamping melihat 2 balasan" (($m.json.messages | Where-Object { $_.sender -eq "Penyintas" }).Count -eq 2)
$m = Api x "/kasus/$code" "/api/kasus/$code/pesan"; Check "tanda dibaca sampai pesan Dewi" ($m.json.read_upto -ge 0 -and ($m.json.messages | Where-Object { $_.mine -and $_.read }).Count -eq 2)
Check "hanya 1 notifikasi pesan untuk pendamping (tidak spam)" ((Sql "SELECT COUNT(*) FROM notifications WHERE user_id=2 AND report_id=$rid AND type='pesan'") -eq "1")
$m = Api x "/kasus/$code" "/api/kasus/$code/pesan" @{ body = ("a" * 2001) }; Check "pesan > 2000 karakter ditolak (422)" ($m.code -eq 422)
Check "pesan terenkripsi di DB" ((Sql "SELECT COUNT(*) FROM messages WHERE body_enc LIKE '%aman%'") -eq "0")
$tmp = "$env:TEMP\haven-smoke-body.json"; [IO.File]::WriteAllText($tmp, '{"body":"x"}')
Check "POST pesan tanpa CSRF = 419" ((curl.exe -s -o NUL -w "%{http_code}" -b (Jar x) -H "Content-Type: application/json" -H "X-Requested-With: fetch" --data-binary "@$tmp" "$B/api/kasus/$code/pesan") -eq "419")

"Modul 5: Jadwal"
$tomorrow = (Get-Date).AddDays(1).ToString("yyyy-MM-dd")
$r = Post dewi "/pendamping/slot" "/pendamping/slot" @{ tanggal = $tomorrow; jam = "10:00"; durasi = "45"; cara = "suara" }; Check "slot dibuka" ((Flash $r) -like "Slot *dibuka*")
$r = Post dewi "/pendamping/slot" "/pendamping/slot" @{ tanggal = $tomorrow; jam = "10:30"; durasi = "45"; cara = "suara" }; Check "slot bertabrakan ditolak" (Has $r "bertabrakan")
$r = Post dewi "/pendamping/slot" "/pendamping/slot" @{ tanggal = (Get-Date).ToString("yyyy-MM-dd"); jam = "00:00"; durasi = "45"; cara = "suara" }; Check "slot di masa lalu ditolak" (Has $r "1 jam dari sekarang")
$r = Post dewi "/pendamping/slot" "/pendamping/slot" @{ tanggal = $tomorrow; jam = "13:00"; durasi = "60"; cara = "tatap"; lokasi = "" }; Check "tatap muka wajib lokasi" (Has $r "lokasi pertemuan")
$null = Post dewi "/pendamping/slot" "/pendamping/slot" @{ tanggal = $tomorrow; jam = "15:00"; durasi = "30"; cara = "tatap"; lokasi = "Ruang konseling" }
$slot1 = Sql "SELECT id FROM companion_slots WHERE companion_id=2 AND TIME(starts_at)='10:00:00'"
$slot2 = Sql "SELECT id FROM companion_slots WHERE companion_id=2 AND TIME(starts_at)='15:00:00'"
$r = Req x "/kasus/$code"; Check "slot tampil untuk penyintas" (Has $r "value=`"$slot1`"")
$r = Post x "/kasus/$code" "/kasus/$code/jadwal" @{ slot_id = $slot1 }; Check "penyintas memesan sesi" ((Flash $r) -like "Sesi * dipesan.")
$r = Post anon "/kasus/$code" "/kasus/$code/jadwal" @{ slot_id = $slot1 }; Check "slot yang sama tidak bisa dipesan lagi" ((Flash $r) -like "*baru saja dipesan*")
$r = Req x "/kasus/$code"; Check "slot terpesan hilang dari pilihan" (-not (Has $r "value=`"$slot1`""))
Check "pendamping dapat notifikasi jadwal" ((Sql "SELECT COUNT(*) FROM notifications WHERE user_id=2 AND type='jadwal'") -eq "1")
$r = Req dewi "/pendamping/slot"; Check "slot tampil Dipesan di halaman pendamping" ((Has $r "Dipesan") -and (Has $r $code))
$r = Post dewi "/pendamping/slot" "/pendamping/slot/$slot1/hapus" @{}; Check "slot terpesan tidak bisa dihapus" ((Flash $r) -like "*sudah dipesan*")
Check "pendamping lain tidak bisa hapus slot Dewi" ((Post sari "/pendamping/slot" "/pendamping/slot/$slot2/hapus" @{}).code -eq 404)
$bk = Sql "SELECT id FROM bookings WHERE slot_id=$slot1 AND status='aktif'"
$r = Post x "/kasus/$code" "/kasus/$code/jadwal/$bk/batal" @{}; Check "penyintas membatalkan sesi" ((Flash $r) -like "*dibatalkan.")
$r = Post x "/kasus/$code" "/kasus/$code/jadwal" @{ slot_id = $slot1 }; Check "slot bisa dipesan lagi setelah dibatalkan" ((Flash $r) -like "Sesi * dipesan.")
$bk = Sql "SELECT id FROM bookings WHERE slot_id=$slot1 AND status='aktif'"
$r = Post dewi "/pendamping/slot" "/pendamping/sesi/$bk/batal" @{}; Check "pendamping membatalkan sesi, penyintas diberi tahu" ((Flash $r) -like "Sesi dibatalkan*" -and (Sql "SELECT COUNT(*) FROM notifications WHERE report_id=$rid AND user_id IS NULL AND title LIKE '%dibatalkan pendamping%'") -eq "1")
$r = Post dewi "/pendamping/slot" "/pendamping/slot/$slot2/hapus" @{}; Check "slot kosong dihapus" ((Flash $r) -like "Slot * dihapus.")

"Modul 9: Direktori & rujukan"
$j = (curl.exe -s "$B/api/layanan?provinsi=Jawa%20Tengah" | Out-String) | ConvertFrom-Json
Check "filter provinsi menyertakan layanan nasional" (($j.services.name -contains "SAPA 129 (Kementerian PPPA)") -and ($j.services | Where-Object { $_.area -like "*Jawa Tengah" }).Count -ge 5 -and -not ($j.services.name -contains "UPTD PPA Provinsi DI Yogyakarta"))
Check "daftar kota mengikuti provinsi" ($j.cities -contains "Banyumas" -and -not ($j.cities -contains "Yogyakarta"))
Check "nomor SAPA tampil (terverifikasi)" (($j.services | Where-Object { $_.name -like "SAPA*" }).phone -eq "129")
Check "layanan belum diverifikasi tidak menampilkan kontak" (@($j.services | Where-Object { -not $_.verified -and $_.phone }).Count -eq 0)
$j = (curl.exe -s "$B/api/layanan?jenis=lbh" | Out-String) | ConvertFrom-Json; Check "filter jenis" ($j.total -eq 1 -and $j.services[0].type -eq "lbh")
$j = (curl.exe -s "$B/api/layanan?q=visum" | Out-String) | ConvertFrom-Json; Check "pencarian tag" ($j.services[0].name -like "RSUD*")
$j = (curl.exe -s "$B/api/layanan?q=%25" | Out-String) | ConvertFrom-Json; Check "karakter wildcard % tidak mengembalikan semuanya" ($j.total -eq 0)
$r = Req g "/layanan?jenis=uptd"; Check "halaman layanan (tanpa JS) memfilter di server" ((Has $r "UPTD PPA Kabupaten Banyumas") -and -not (Has $r "LBH APIK"))
$svc = Sql "SELECT id FROM services WHERE name='UPTD PPA Kabupaten Banyumas'"
$r = Post dewi "/pendamping/kasus/$code" "/pendamping/kasus/$code/status" @{ status = "dirujuk"; sepakat = "1" }; Check "rujukan tanpa layanan ditolak" (Has $r "Pilih layanan tujuan rujukan.")
$r = Post dewi "/pendamping/kasus/$code" "/pendamping/kasus/$code/status" @{ status = "dirujuk"; layanan = $svc }; Check "tanpa persetujuan penyintas ditolak" (Has $r "Pastikan penyintas sudah setuju.")
$r = Post dewi "/pendamping/kasus/$code" "/pendamping/kasus/$code/status" @{ status = "ditinjau"; sepakat = "1" }; Check "transisi mundur ditolak" (Has $r "Pilih status berikutnya.")
$r = Post dewi "/pendamping/kasus/$code" "/pendamping/kasus/$code/status" @{ status = "dirujuk"; layanan = $svc; sepakat = "1"; catatan = "Kami hubungkan dengan UPTD." }; Check "dirujuk ke UPTD Banyumas" ((Flash $r) -like "*Dirujuk*")
$r = Req x "/kasus/$code"; Check "penyintas melihat layanan rujukan & catatan" ((Has $r "UPTD PPA Kabupaten Banyumas") -and (Has $r "Kami hubungkan dengan UPTD.") -and (Has $r "Rujukan baru tersedia"))
$r = Post dewi "/pendamping/kasus/$code" "/pendamping/kasus/$code/status" @{ status = "selesai"; sepakat = "1" }; Check "kasus selesai" ((Flash $r) -like "*Selesai*")
$m = Api x "/kasus/$code" "/api/kasus/$code/pesan" @{ body = "masih bisa?" }; Check "percakapan ditutup setelah selesai (409)" ($m.code -eq 409)
Check "6 log status lengkap" ((Sql "SELECT GROUP_CONCAT(to_status ORDER BY id) FROM report_status_logs WHERE report_id=$rid") -eq "diterima,ditinjau,didampingi,dirujuk,selesai")

"Admin: layanan"
$r = Post adm "/admin/layanan/baru" "/admin/layanan" @{ name = "Layanan Uji"; type = "lbh"; province = "Jawa Tengah"; city = "Banyumas"; is_verified = "1" }; Check "verifikasi tanpa tanggal/cara/kontak ditolak" ((Has $r "Isi tanggal verifikasi.") -or (Has $r "Catat cara verifikasinya.")) 
$r = Post adm "/admin/layanan/baru" "/admin/layanan" @{ name = "Layanan Uji"; type = "lbh"; province = "Jawa Tengah"; city = "Banyumas"; phone = "0281-000"; is_verified = "1"; verified_at = (Get-Date).ToString("yyyy-MM-dd"); verified_note = "Telepon kantor" }; Check "layanan baru terverifikasi ditambahkan" ((Flash $r) -like "*ditambahkan*")
$j = (curl.exe -s "$B/api/layanan?q=Layanan%20Uji" | Out-String) | ConvertFrom-Json; Check "kontak layanan baru tampil di publik" ($j.services[0].phone -eq "0281-000")
$r = Post adm "/admin/layanan/$svc" "/admin/layanan/$svc/hapus" @{}; Check "layanan yang dipakai rujukan tidak bisa dihapus" ((Flash $r) -like "*tidak bisa dihapus*")
$new = Sql "SELECT id FROM services WHERE name='Layanan Uji'"
$r = Post adm "/admin/layanan/$new" "/admin/layanan/$new/hapus" @{}; Check "layanan uji dihapus" ((Flash $r) -like "*dihapus.")
Check "pendamping tidak bisa membuka admin layanan" ((Req dewi "/admin/layanan").code -eq 403)

"Admin: tutup laporan"
$b2 = $base.Clone(); $b2.kontak_email = ""; $b2.email = ""; $b2.bahaya = "ya"
$r = Post anon2 "/lapor" "/lapor" $b2; $code2 = [regex]::Match($r.body, '<dd>(HV-[A-Z0-9]{6})</dd>').Groups[1].Value
Check "laporan 'dalam bahaya' urgensi tinggi & teratas di antrean" ((Sql "SELECT urgency FROM reports WHERE code='$code2'") -eq "tinggi" -and [regex]::Match((Req adm "/admin/antrean").body, 'HV-[A-Z0-9]{6}').Value -eq $code2)
$r = Post adm "/admin/kasus/$code2" "/admin/kasus/$code2/tutup" @{ alasan = "spam" }; Check "alasan pendek ditolak" ((Flash $r) -like "*minimal 10 karakter*")
$r = Post adm "/admin/kasus/$code2" "/admin/kasus/$code2/tutup" @{ alasan = "Duplikat dari laporan sebelumnya." }; Check "laporan ditutup" ((Flash $r) -like "*ditutup*")
$r = Req anon2 "/kasus/$code2"; Check "penyintas melihat status Ditutup & alasannya" ((Has $r "Ditutup") -and (Has $r "Duplikat dari laporan sebelumnya."))

"Akun penyintas"
$null = Login rani "rani@demo.id"
$r = Req rani "/lapor"; Check "opsi 'Dengan akun' muncul untuk penyintas" (Has $r 'value="akun"')
$b3 = $base.Clone(); $b3.identitas = "akun"; $b3.kontak_email = ""; $b3.email = ""
$r = Post rani "/lapor" "/lapor" $b3; $code3 = [regex]::Match($r.body, '<dd>(HV-[A-Z0-9]{6})</dd>').Groups[1].Value
Check "laporan berakun tersimpan untuk Rani" ((Sql "SELECT user_id FROM reports WHERE code='$code3'") -eq "3")
$r = Req rani "/kasus/$code3"; Check "laporan berakun dibuka di layout aplikasi" ((Has $r "app-body") -and (Has $r "Laporan saya"))
$b4 = $base.Clone(); $b4.identitas = "akun"
$r = Post anon "/lapor" "/lapor" $b4; Check "tamu tidak bisa memilih 'Dengan akun'" (Has $r "Masuk sebagai penyintas dulu")
$r = Post rani "/penyintas/laporan" "/penyintas/laporan/tautkan" @{ kode = $code; pin = $pin }; Check "laporan anonim ditautkan ke akun" ((Flash $r) -like "*sekarang ada di akunmu*" -and (Has $r $code) -and (Has $r $code3))
Check "notifikasi anonim pindah ke akun" ((Sql "SELECT COUNT(*) FROM notifications WHERE report_id=$rid AND user_id IS NULL") -eq "0")
$r = Req rani "/penyintas/jadwal"; Check "halaman jadwal penyintas" ($r.code -eq 200 -and (Has $r "Sudah lewat"))

"Modul 11: Notifikasi & audit"
$r = Req dewi "/notifikasi"; Check "halaman notifikasi pendamping" (Has $r "Kasus baru ditugaskan: $code")
$nid = Sql "SELECT id FROM notifications WHERE user_id=2 AND read_at IS NULL ORDER BY id LIMIT 1"
$r = Req dewi "/notifikasi/$nid"; Check "membuka notifikasi menandai dibaca & mengarahkan" ((Sql "SELECT read_at IS NOT NULL FROM notifications WHERE id=$nid") -eq "1" -and $r.code -eq 200)
Check "notifikasi orang lain = 404" ((Req sari "/notifikasi/$nid").code -eq 404)
$r = Post dewi "/notifikasi" "/notifikasi/baca-semua" @{}; Check "tandai semua dibaca" ((Api dewi "/notifikasi" "/api/notifikasi").json.unread -eq 0)
$r = Req adm "/admin/audit?q=$code"; Check "audit log mencatat akses pendamping" ((Has $r "Membuka detail kasus") -and (Has $r "Dewi Anggraini"))
$r = Req adm "/admin/audit?aksi=denied"; Check "filter audit: akses ditolak" (Has $r "Akses ditolak")

"Rate limit lacak"
1..5 | ForEach-Object { $null = Post lock "/lacak" "/lacak" @{ kode = "HV-ZZZZZZ"; pin = "111111" } }
$r = Post lock "/lacak" "/lacak" @{ kode = $code; pin = $pin }; Check "setelah 5 kali salah, PIN benar pun dikunci" (Has $r "dikunci sementara")
& C:\xampp\php\php.exe -r "require '$($PSScriptRoot -replace '\\','/')/../app/bootstrap.php'; Database::run('DELETE FROM rate_limits');"

Done
