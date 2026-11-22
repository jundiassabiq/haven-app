# End-to-end test for Tasks 11–13: education, statistics. Needs a running server and a freshly reset database.
#   C:\xampp\php\php.exe bin\db-reset.php
#   powershell -ExecutionPolicy Bypass -File tests\smoke-content.ps1
param([string]$B = "http://127.0.0.1:8000")
. "$PSScriptRoot\smoke-lib.ps1"

"Modul 7: Edukasi"
$r = Req g "/edukasi"; Check "daftar artikel menampilkan 7 artikel" ($r.code -eq 200 -and ([regex]::Matches($r.body, 'class="article-card"')).Count -eq 7)
$r = Req g "/edukasi?kategori=mengenali"; Check "filter kategori Mengenali (3 artikel)" (([regex]::Matches($r.body, 'class="article-card"')).Count -eq 3)
$r = Req g "/edukasi?q=consent"; Check "pencarian 'consent'" ((Has $r "Memahami consent") -and ([regex]::Matches($r.body, 'class="article-card"')).Count -ge 1)
$r = Req g "/edukasi?q=%25%25"; Check "pencarian karakter wildcard tidak mengembalikan semua" (([regex]::Matches($r.body, 'class="article-card"')).Count -eq 0 -and (Has $r "Belum ada artikel yang cocok"))
$r = Req g "/edukasi?kategori=palsu"; Check "kategori tidak dikenal diabaikan" (([regex]::Matches($r.body, 'class="article-card"')).Count -eq 7)
$r = Req g "/edukasi/sembilan-bentuk-kekerasan-seksual"; Check "halaman artikel: isi, subjudul, sumber rujukan" ((Has $r "<h2>9. Kekerasan seksual berbasis elektronik</h2>") -and (Has $r "Sumber rujukan") -and (Has $r 'href="https://peraturan.bpk.go.id/"'))
Check "artikel: kotak sorotan & tautan internal" ((Has $r 'class="callout"') -and (Has $r 'href="/lapor"'))
Check "artikel: bacaan terkait" (Has $r "Bacaan terkait")
Check "slug tak dikenal = 404" ((Req g "/edukasi/tidak-ada").code -eq 404)
$r = Req g "/"; Check "beranda menampilkan 3 artikel terbaru dari database" (([regex]::Matches($r.body, 'class="article-card"')).Count -eq 3 -and (Has $r "Sembilan bentuk"))
Check "beranda: angka dampak dari database" (Has $r 'data-count="0"')

$null = Login adm "admin@demo.id"
$r = Req adm "/admin/artikel"; Check "admin: daftar artikel" ((Has $r "Mengenali grooming") -and (Has $r "Terbit"))
$r = Post adm "/admin/artikel/baru" "/admin/artikel" @{ title = "Uji"; excerpt = ""; body = "pendek"; sources = "tanpa url"; category_id = "1"; status = "terbit" }; Check "validasi artikel" ((Has $r "Tulis ringkasan") -and (Has $r "minimal 100 karakter"))
$body = "Paragraf pembuka artikel uji yang cukup panjang untuk lolos validasi minimal seratus karakter.`n`n## Bagian`n`n- poin satu`n- poin dua`n`n<script>alert(1)</script>"
$r = Post adm "/admin/artikel/baru" "/admin/artikel" @{ title = "Artikel Uji Draf"; excerpt = "Ringkasan uji."; body = $body; sources = "Komnas Perempuan | https://komnasperempuan.go.id/"; category_id = "2"; status = "draf"; slug = ""; stock_cover = "img/phone.jpg" }
Check "draf tersimpan dengan slug otomatis" ((Flash $r) -eq "Draf disimpan." -and (Sql "SELECT slug FROM articles WHERE title='Artikel Uji Draf'") -eq "artikel-uji-draf")
Check "draf tidak terlihat di publik" ((Req g "/edukasi/artikel-uji-draf").code -eq 404)
$aid = Sql "SELECT id FROM articles WHERE slug='artikel-uji-draf'"
$r = Req adm "/admin/artikel/$aid/pratinjau"; Check "pratinjau admin untuk draf, HTML di-escape" ((Has $r "Pratinjau admin") -and (Has $r "&lt;script&gt;") -and -not (Has $r "<script>alert"))
$r = Post adm "/admin/artikel/$aid" "/admin/artikel/$aid" @{ title = "Artikel Uji Draf"; excerpt = "Ringkasan uji."; body = $body; sources = "Komnas Perempuan | https://komnasperempuan.go.id/"; category_id = "2"; status = "terbit"; slug = "artikel-uji-draf"; stock_cover = "" }
Check "diterbitkan: terlihat di publik" ((Req g "/edukasi/artikel-uji-draf").code -eq 200 -and (Sql "SELECT published_at IS NOT NULL FROM articles WHERE id=$aid") -eq "1")
$r = Post adm "/admin/artikel/$aid" "/admin/artikel/$aid" @{ title = "X"; excerpt = "x"; body = $body; sources = "a | https://a.id/"; category_id = "2"; status = "terbit"; slug = "hak-korban" }; Check "slug ganda ditolak" (Has $r "sudah dipakai artikel lain")
$png = "$env:TEMP\haven-cover.png"; [IO.File]::WriteAllBytes($png, [Convert]::FromBase64String("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=="))
$r = Post adm "/admin/artikel/$aid" "/admin/artikel/$aid" @{ title = "Artikel Uji Draf"; excerpt = "Ringkasan uji."; body = $body; sources = "Komnas Perempuan | https://komnasperempuan.go.id/"; category_id = "2"; status = "terbit"; slug = "artikel-uji-draf" } @{ cover = "$png;type=image/png" }
$cover = Sql "SELECT cover_path FROM articles WHERE id=$aid"
Check "sampul diunggah ke public/uploads/covers" ($cover -like "uploads/covers/*.png" -and (Test-Path "$PSScriptRoot\..\public\$cover"))
Check "sampul tersaji" ((curl.exe -s -o NUL -w "%{http_code}" "$B/$cover") -eq "200")
$r = Post adm "/admin/artikel" "/admin/artikel/$aid/hapus" @{}; Check "artikel dihapus beserta sampulnya" ((Flash $r) -like "*dihapus." -and -not (Test-Path "$PSScriptRoot\..\public\$cover"))
$null = Login dewi "dewi@demo.id"
Check "pendamping tidak bisa membuka CMS artikel" ((Req dewi "/admin/artikel").code -eq 403)

"Modul 10: Statistik"
$r = Req adm "/admin"; Check "dashboard admin: KPI & kanvas grafik" ((Has $r "Laporan masuk") -and ([regex]::Matches($r.body, 'data-chart-key=')).Count -eq 3)
$j = Api adm "/admin" "/api/statistik?rentang=12b"; Check "API statistik: 3 grafik, 12 bulan" ($j.code -eq 200 -and $j.json.trend.labels.Count -eq 12 -and $j.json.status.series.Count -eq 2)
Check "API statistik hanya untuk admin" ((Api dewi "/pendamping" "/api/statistik").code -eq 403)
$r = Req dewi "/pendamping"; Check "KPI pendamping" ((Has $r "Belum disapa") -and (Has $r "Sesi hari ini"))
$r = Req adm "/admin?rentang=30h"; Check "filter rentang" (Has $r 'value="30h" selected')

Done
