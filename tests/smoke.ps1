# End-to-end smoke test against a running server (default http://127.0.0.1:8000) with a freshly reset database.
#   powershell -ExecutionPolicy Bypass -File tests\smoke.ps1
param([string]$B = "http://127.0.0.1:8000", [string]$Php = "C:\xampp\php\php.exe")
. "$PSScriptRoot\smoke-lib.ps1"

"Publik"
$r = Req g "/"; Check "beranda 200" ($r.code -eq 200 -and (Has $r "Laporkan kekerasan seksual tanpa menyebut nama."))
Check "tombol keluar cepat ada" (Has $r "data-quick-exit")
Check "tentang 200" ((Req g "/tentang").code -eq 200)
Check "tips-aman 200" ((Req g "/tips-aman").code -eq 200)
$r = Req g "/tidak-ada"; Check "rute tak dikenal 404" ($r.code -eq 404 -and (Has $r "tidak ditemukan"))
Check "aset CSS tersaji" ((Req g "/assets/css/app.css").code -eq 200)

"Proteksi"
$r = Req g "/penyintas"; Check "tamu ke /penyintas diarahkan ke /masuk" ($r.url -eq "/masuk" -and (Flash $r) -like "*Masuk dulu*")
$r = Req g "/masuk" @{ email = "rani@demo.id"; password = "haven123" }; Check "POST tanpa CSRF ditolak 419" ($r.code -eq 419)

"Login"
$r = Post a "/masuk" "/masuk" @{ email = ""; password = "" }; Check "isian kosong menampilkan error field" ((Has $r "Masukkan email akunmu.") -and (Has $r 'aria-invalid="true"'))
$r = Post a "/masuk" "/masuk" @{ email = "rani@demo.id"; password = "salah" }; Check "kata sandi salah" (Has $r "Email atau kata sandi salah.")
Check "email tetap terisi setelah gagal" (Has $r 'value="rani@demo.id"')
$r = Post a "/masuk" "/masuk" @{ email = "RANI@Demo.id"; password = "haven123" }; Check "penyintas masuk ke /penyintas" ($r.url -eq "/penyintas" -and (Has $r "Halo, Rani."))
Check "/masuk dialihkan saat sudah login" ((Req a "/masuk").url -eq "/penyintas")
Check "penyintas ke /admin = 403" ((Req a "/admin").code -eq 403)
Check "penyintas ke /pendamping = 403" ((Req a "/pendamping").code -eq 403)
Check "penyintas ke /admin/pengguna = 403" ((Req a "/admin/pengguna").code -eq 403)

"Profil"
$r = Post a "/akun" "/akun/profil" @{ name = "Rani"; email = "admin@demo.id" }; Check "email milik akun lain ditolak" (Has $r "Email ini sudah dipakai akun lain.")
$r = Post a "/akun" "/akun/profil" @{ name = "Rani S"; email = "rani@demo.id"; allow_email = "1"; allow_whatsapp = "1"; safe_time = "Malam" }; Check "profil tersimpan" ((Flash $r) -eq "Profil disimpan." -and (Has $r 'value="Rani S"') -and (Has $r 'value="Malam"'))
$r = Post a "/akun" "/akun/kata-sandi" @{ current_password = "keliru"; password = "barubaru1"; password_confirm = "barubaru1" }; Check "kata sandi lama salah ditolak" (Has $r "Kata sandi saat ini salah.")
$r = Post a "/akun" "/akun/kata-sandi" @{ current_password = "haven123"; password = "pendek"; password_confirm = "pendek" }; Check "kata sandi baru < 8 ditolak" (Has $r "minimal 8 karakter")

"Sesi lain dikeluarkan saat ganti kata sandi"
$r = Post b "/masuk" "/masuk" @{ email = "rani@demo.id"; password = "haven123" }; Check "sesi kedua masuk" ($r.url -eq "/penyintas")
$r = Post a "/akun" "/akun/kata-sandi" @{ current_password = "haven123"; password = "rahasia-baru-1"; password_confirm = "rahasia-baru-1" }; Check "kata sandi diganti, sesi ini tetap masuk" ((Flash $r) -like "Kata sandi diperbarui*" -and $r.url -eq "/akun")
Check "sesi kedua otomatis keluar" ((Req b "/penyintas").url -eq "/masuk")
$r = Post a "/akun" "/akun/kata-sandi" @{ current_password = "rahasia-baru-1"; password = "haven123"; password_confirm = "haven123" }; Check "kata sandi dikembalikan" ((Flash $r) -like "Kata sandi diperbarui*")

"Logout"
$r = Post a "/penyintas" "/keluar" @{}; Check "keluar ke /masuk" ($r.url -eq "/masuk" -and (Flash $r) -eq "Kamu sudah keluar dari akun.")
Check "setelah keluar /penyintas terkunci" ((Req a "/penyintas").url -eq "/masuk")

"Pendaftaran"
$r = Post c "/daftar" "/daftar" @{ name = ""; email = "x"; password = "123"; password_confirm = "9"; agree = "" }; Check "semua error pendaftaran tampil" ((Has $r "Tulis nama panggilan") -and (Has $r "Format email belum benar") -and (Has $r "minimal 8") -and (Has $r "tidak sama") -and (Has $r "Centang untuk melanjutkan"))
$r = Post c "/daftar" "/daftar" @{ name = "Uji"; email = "rani@demo.id"; password = "kata-sandi-1"; password_confirm = "kata-sandi-1"; agree = "1" }; Check "email terdaftar ditolak" (Has $r "Email ini sudah terdaftar")
$email = "uji" + (Get-Random) + "@demo.id"
$r = Post c "/daftar" "/daftar" @{ name = "Uji"; email = $email; password = "kata-sandi-1"; password_confirm = "kata-sandi-1"; agree = "1" }; Check "akun baru langsung masuk sebagai penyintas" ($r.url -eq "/penyintas" -and (Flash $r) -like "Akunmu sudah dibuat*")

"Lupa & reset kata sandi"
$log = "$PSScriptRoot\..\storage\mail.log"; $before = if (Test-Path $log) { (Get-Content $log -Raw).Length } else { 0 }
$r = Post d "/lupa-password" "/lupa-password" @{ email = "tidakada@demo.id" }; Check "email tak dikenal: pesan sama (tidak bocor)" (Has $r "Cek kotak masukmu.")
$r = Post d "/lupa-password" "/lupa-password" @{ email = "dewi@demo.id" }; Check "email dikenal: pesan sama" (Has $r "Cek kotak masukmu.")
$mail = (Get-Content $log -Raw).Substring($before); $tok = [regex]::Matches($mail, 'token=([a-f0-9]{64})') | Select-Object -Last 1
Check "hanya satu email terkirim (untuk dewi)" (([regex]::Matches($mail, 'Kepada  : ')).Count -eq 1 -and $mail.Contains("dewi@demo.id"))
$t = $tok.Groups[1].Value
$r = Req d "/reset-password?token=$t"; Check "halaman reset menampilkan email tersamar" (Has $r "de***@demo.id")
$r = Req d "/reset-password?token=$("0" * 64)"; Check "token palsu: tidak berlaku" (Has $r "Tautan tidak berlaku")
$r = Post d "/reset-password?token=$t" "/reset-password" @{ token = $t; password = "dewi-baru-123"; password_confirm = "dewi-baru-123" }; Check "reset berhasil ke /masuk" ($r.url -eq "/masuk" -and (Flash $r) -like "Kata sandi diperbarui*")
$r = Req d "/reset-password?token=$t"; Check "token sekali pakai" (Has $r "Tautan tidak berlaku")
$r = Post d "/masuk" "/masuk" @{ email = "dewi@demo.id"; password = "dewi-baru-123" }; Check "pendamping masuk dengan sandi baru ke /pendamping" ($r.url -eq "/pendamping")
Check "pendamping ke /admin = 403" ((Req d "/admin").code -eq 403)
Check "pendamping ke /akun/hapus = 403" ((Post d "/akun" "/akun/hapus" @{ confirm = "HAPUS" }).code -eq 403)
$r = Post d "/akun" "/akun/kata-sandi" @{ current_password = "dewi-baru-123"; password = "haven123"; password_confirm = "haven123" }; Check "sandi dewi dikembalikan" ((Flash $r) -like "Kata sandi diperbarui*")

"Admin: pengguna"
$r = Post e "/masuk" "/masuk" @{ email = "admin@demo.id"; password = "haven123" }; Check "admin masuk ke /admin" ($r.url -eq "/admin" -and (Has $r "Statistik"))
$r = Req e "/admin/pengguna"; Check "daftar staf tanpa penyintas" ((Has $r "Dewi Anggraini") -and -not (Has $r "rani@demo.id"))
$new = "pendamping" + (Get-Random) + "@demo.id"
$r = Post e "/admin/pengguna" "/admin/pengguna" @{ name = "Pendamping Baru"; email = $new; role = "companion"; region = "Cilacap" }; Check "undangan pendamping dibuat" ((Flash $r) -like "Akun dibuat*" -and (Has $r $new))
$r = Post e "/admin/pengguna" "/admin/pengguna" @{ name = "X"; email = $new; role = "root" }; Check "peran tak valid & email ganda ditolak" ((Has $r "Email ini sudah terdaftar") -and (Has $r "Pilihan ini tidak tersedia"))
$r = Post e "/admin/pengguna" "/admin/pengguna/5/status" @{ active = "0" }; Check "nonaktifkan Bayu" ((Flash $r) -like "*dinonaktifkan*")
$r = Post f "/masuk" "/masuk" @{ email = "bayu@demo.id"; password = "haven123" }; Check "akun nonaktif tidak bisa masuk" (Has $r "Akun ini dinonaktifkan")
$r = Post e "/admin/pengguna" "/admin/pengguna/5/status" @{ active = "1" }; Check "aktifkan Bayu kembali" ((Flash $r) -like "*diaktifkan kembali*")
Check "admin tidak bisa ubah dirinya sendiri" ((Post e "/admin/pengguna" "/admin/pengguna/1/status" @{ active = "0" }).code -eq 403)
Check "admin tidak bisa ubah akun penyintas" ((Post e "/admin/pengguna" "/admin/pengguna/3/status" @{ active = "0" }).code -eq 404)

"Rate limit login"
$x = "kunci" + (Get-Random) + "@demo.id"
1..5 | ForEach-Object { $null = Post g "/masuk" "/masuk" @{ email = $x; password = "salah" } }
$r = Post g "/masuk" "/masuk" @{ email = $x; password = "salah" }; Check "percobaan ke-6 dikunci" (Has $r "Terlalu banyak percobaan masuk")
# The lockout above also blocks 127.0.0.1; clear it so you can sign in right after the test.
& $Php -r "require '$($PSScriptRoot -replace '\\','/')/../app/bootstrap.php'; Database::run('DELETE FROM rate_limits');"

Done
