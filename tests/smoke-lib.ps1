# Shared helpers for the curl-based smoke tests. Dot-source this file.
$ErrorActionPreference = "Stop"
# curl prints UTF-8; without this, PowerShell 5 decodes it with the console code page and "·" turns into mojibake.
[Console]::OutputEncoding = [Text.Encoding]::UTF8
$script:fail = 0
$script:jars = @{}

function Jar($who) { if (-not $script:jars[$who]) { $script:jars[$who] = "$env:TEMP\haven-smoke-$who.txt"; Remove-Item $script:jars[$who] -ErrorAction SilentlyContinue }; $script:jars[$who] }

# Request that follows redirects (303 after POST becomes GET). Returns @{ code; url; body }
# $data: url-encoded fields. $files: @{ "field" = "path;type=mime" } switches to multipart.
function Req($who, $path, $data = $null, $files = $null, $headers = $null) {
  $j = Jar $who
  $a = @("-s", "-L", "-c", $j, "-b", $j, "-w", "`n%{http_code} %{url_effective}")
  if ($headers) { foreach ($k in $headers.Keys) { $a += @("-H", "${k}: $($headers[$k])") } }
  if ($files) {
    foreach ($k in $data.Keys) { $a += @("-F", "$k=$($data[$k])") }
    foreach ($k in $files.Keys) { foreach ($f in @($files[$k])) { $a += @("-F", "$k=@$f") } }
  } elseif ($data) { foreach ($k in $data.Keys) { $a += @("--data-urlencode", "$k=$($data[$k])") } }
  $out = (& curl.exe @a "$B$path" | Out-String).TrimEnd()
  $i = $out.LastIndexOf("`n")
  $meta = $out.Substring($i + 1).Split(" ")
  @{ code = [int]$meta[0]; url = ([uri]$meta[1]).PathAndQuery; body = $out.Substring(0, [Math]::Max(0, $i)) }
}
function Token($who, $path) { [regex]::Match((Req $who $path).body, 'name="_csrf" value="([a-f0-9]+)"').Groups[1].Value }
function Post($who, $from, $path, $data, $files = $null) { $data["_csrf"] = Token $who $from; Req $who $path $data $files }

# JSON API call with the CSRF token from a page the user can open.
function Api($who, $from, $path, $json = $null) {
  $j = Jar $who
  $t = Token $who $from
  $a = @("-s", "-c", $j, "-b", $j, "-w", "`n%{http_code}", "-H", "Accept: application/json", "-H", "X-Requested-With: fetch")
  if ($json -ne $null) {
    $tmp = "$env:TEMP\haven-smoke-body.json"; [IO.File]::WriteAllText($tmp, ($json | ConvertTo-Json -Compress), (New-Object Text.UTF8Encoding $false))
    $a += @("-X", "POST", "-H", "Content-Type: application/json", "-H", "X-CSRF-Token: $t", "--data-binary", "@$tmp")
  }
  $out = (& curl.exe @a "$B$path" | Out-String).TrimEnd()
  $i = $out.LastIndexOf("`n")
  $body = $out.Substring(0, [Math]::Max(0, $i))
  @{ code = [int]$out.Substring($i + 1); json = $(try { $body | ConvertFrom-Json } catch { $null }); body = $body }
}

function Check($name, $cond, $detail = "") { if ($cond) { "  PASS  $name" } else { $script:fail++; "  FAIL  $name  $detail" } }
function Flash($r) { ([regex]::Matches($r.body, 'data-flash="([^"]*)"') | ForEach-Object { [System.Net.WebUtility]::HtmlDecode($_.Groups[1].Value) }) -join " | " }
function Has($r, $text) { $r.body.Contains($text) }
function Sql($q) { (& C:\xampp\mysql\bin\mysql.exe -u root -N -B -e $q haven_v2 | Out-String).Trim() }
function Login($who, $email, $pw = "haven123") { Post $who "/masuk" "/masuk" @{ email = $email; password = $pw } }
function Done() { ""; if ($script:fail -eq 0) { "Semua cek lulus." } else { "$($script:fail) cek gagal."; exit 1 } }
