// Real-browser checks of the JavaScript behaviour (headless Edge via the DevTools protocol, no npm packages).
//   C:\xampp\php\php.exe bin\db-reset.php
//   node tests/browser.mjs
// Needs the dev server on http://127.0.0.1:8000 and Node 22+ (built-in WebSocket).
import { spawn, execFileSync } from "node:child_process";
import { mkdtempSync, existsSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";

const BASE = process.env.HAVEN_URL || "http://127.0.0.1:8000";
const PHP = process.env.PHP || "C:\\xampp\\php\\php.exe";
const EDGE = ["C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe", "C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe"].find(existsSync);
const PORT = 9333;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
let fails = 0;
const check = (name, ok, detail = "") => { console.log(`  ${ok ? "PASS" : "FAIL"}  ${name}${ok || !detail ? "" : "  " + detail}`); if (!ok) fails++; };
const php = (code) => execFileSync(PHP, ["-r", `require 'app/bootstrap.php'; ${code}`], { cwd: new URL("..", import.meta.url).pathname.slice(1), encoding: "utf8" });

// ---------- minimal CDP client ----------
const edge = spawn(EDGE, ["--headless=new", "--disable-gpu", `--remote-debugging-port=${PORT}`, `--user-data-dir=${mkdtempSync(join(tmpdir(), "haven-cdp-"))}`, "--no-first-run", "about:blank"], { stdio: "ignore" });
let ver;
for (let i = 0; i < 50 && !ver; i++) { try { ver = await (await fetch(`http://127.0.0.1:${PORT}/json/version`)).json(); } catch { await sleep(200); } }
const ws = new WebSocket(ver.webSocketDebuggerUrl);
await new Promise((r) => (ws.onopen = r));
let seq = 0;
const pending = new Map();
const listeners = [];
ws.onmessage = (ev) => {
  const m = JSON.parse(ev.data);
  if (m.id && pending.has(m.id)) { const { res, rej } = pending.get(m.id); pending.delete(m.id); m.error ? rej(new Error(m.error.message)) : res(m.result); }
  else listeners.forEach((l) => l(m));
};
const send = (method, params = {}, sessionId) => new Promise((res, rej) => { const id = ++seq; pending.set(id, { res, rej }); ws.send(JSON.stringify({ id, method, params, sessionId })); });

const problems = [];
const missing = new Set();
async function newPage(label) {
  const { browserContextId } = await send("Target.createBrowserContext");
  const { targetId } = await send("Target.createTarget", { url: "about:blank", browserContextId });
  const { sessionId } = await send("Target.attachToTarget", { targetId, flatten: true });
  for (const d of ["Page", "Runtime", "Log", "Network"]) await send(`${d}.enable`, {}, sessionId);
  await send("Emulation.setDeviceMetricsOverride", { width: 1280, height: 900, deviceScaleFactor: 1, mobile: false }, sessionId);
  const requests = [];
  listeners.push((m) => {
    if (m.sessionId !== sessionId) return;
    const ours = (u) => !u || u.startsWith(BASE);
    if (m.method === "Runtime.exceptionThrown" && (m.params.exceptionDetails.url || "").startsWith(BASE)) problems.push(`[${label}] JS error: ${m.params.exceptionDetails.exception?.description || m.params.exceptionDetails.text}`);
    if (m.method === "Log.entryAdded" && m.params.entry.level === "error" && ours(m.params.entry.url) && !/Failed to load resource/.test(m.params.entry.text)) problems.push(`[${label}] ${m.params.entry.text}`);
    if (m.method === "Network.responseReceived" && m.params.response.status >= 400 && ours(m.params.response.url)) missing.add(`${m.params.response.status} ${m.params.response.url.replace(BASE, "")}`);
    if (m.method === "Network.requestWillBeSent") requests.push(m.params.request.url);
  });
  const ev = async (expr) => {
    const r = await send("Runtime.evaluate", { expression: `(async () => { ${expr} })()`, awaitPromise: true, returnByValue: true }, sessionId);
    if (r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description || r.exceptionDetails.text);
    return r.result.value;
  };
  const go = async (path) => {
    await send("Page.navigate", { url: BASE + path }, sessionId);
    await waitFor(ev, `document.readyState === "complete" && location.href.startsWith(${JSON.stringify(BASE)})`);
    await sleep(150);
  };
  return { ev, go, requests, sessionId };
}
async function waitFor(ev, cond, ms = 6000) {
  const t = Date.now();
  while (Date.now() - t < ms) { try { if (await ev(`return !!(${cond})`)) return true; } catch {} await sleep(100); }
  return false;
}
const pick = (name, value) => `{ const i = document.querySelector('input[name="${name}"][value="${value}"]'); i.checked = true; i.dispatchEvent(new Event("change", { bubbles: true })); }`;
const fill = (sel, v) => `{ const i = document.querySelector(${JSON.stringify(sel)}); i.value = ${JSON.stringify(v)}; i.dispatchEvent(new Event("input", { bubbles: true })); i.dispatchEvent(new Event("change", { bubbles: true })); }`;
const visibleStep = `[...document.querySelectorAll("[data-step]")].findIndex((s) => !s.hidden)`;

try {
  // ---------- Stepper ----------
  console.log("Stepper laporan");
  const a = await newPage("penyintas");
  await a.go("/lapor");
  await a.ev(`document.querySelector("[data-next]").click()`);
  check("Lanjut tanpa memilih menampilkan error & tetap di langkah 1", await a.ev(`return !!document.getElementById("jenis-err") && ${visibleStep} === 0`));
  await a.ev(`${pick("jenis", "fisik")} ${pick("bahaya", "ya")}`);
  check("error hilang setelah memilih", await a.ev(`return !document.getElementById("jenis-err")`));
  check("peringatan bahaya muncul saat memilih 'Ya'", await a.ev(`return !document.querySelector('[data-show-when="bahaya=ya"]').hidden`));
  await a.ev(`document.querySelector("[data-next]").click()`);
  check("pindah ke langkah 2, penghitung langkah diperbarui", await a.ev(`return ${visibleStep} === 1 && document.querySelector("[data-step-count]").textContent === "Langkah 2 dari 4"`));
  const story = "Saya disentuh tanpa izin oleh rekan kerja di gudang. UJI-BROWSER.";
  await a.ev(fill("#kronologi", story));
  check("penghitung karakter kronologi", await a.ev(`return document.getElementById("kronologi-count").textContent === "${story.length} / 5.000"`), await a.ev(`return document.getElementById("kronologi-count").textContent`));
  await a.ev(`${pick("berlangsung", "tidak")} document.querySelector("[data-next]").click()`);
  await a.ev(`document.querySelector("#provinsi").value = "Jawa Tengah"; document.querySelector("[data-next]").click()`);
  check("langkah 4: ringkasan terisi", await a.ev(`return document.querySelector('[data-summary="jenis"]').textContent === "Pelecehan fisik" && document.querySelector('[data-summary="provinsi"]').textContent === "Jawa Tengah"`));
  await a.ev(`{ const c = document.querySelector('[name="kontak_email"]'); c.checked = true; c.dispatchEvent(new Event("change", { bubbles: true })); }`);
  check("centang Email memunculkan kolom email", await a.ev(`return !document.querySelector('[data-show-when="kontak_email=1"]').hidden`));
  await a.ev(`document.querySelector('[name="setuju"]').checked = true; document.querySelector("[data-submit]").click()`);
  await sleep(300);
  check("kirim diblokir bila email kosong", await a.ev(`return location.pathname === "/lapor" && !!document.getElementById("email-err")`));
  await a.ev(`{ const c = document.querySelector('[name="kontak_email"]'); c.checked = false; c.dispatchEvent(new Event("change", { bubbles: true })); } document.querySelector("[data-submit]").click()`);
  const sent = await waitFor(a.ev, `location.pathname === "/lapor/terkirim"`);
  check("laporan terkirim lewat stepper", sent);
  const code = await a.ev(`return document.querySelector(".secret dd").textContent.trim()`);
  check("kode laporan valid", /^HV-[A-Z0-9]{6}$/.test(code), code);

  // ---------- Chat polling ----------
  console.log("Pesan aman (polling)");
  php(`$r = Report::findByCode('${code}'); $r = CaseWorkflow::transition($r, 'ditinjau'); $r = CaseWorkflow::transition($r, 'didampingi', ['companion_id' => 2]); Message::create((int) $r['id'], 'companion', 2, 'Halo, aku Dewi. UJI-PESAN-1');`);
  await a.go(`/kasus/${code}#pesan`);
  check("hash #pesan membuka tab Pesan", await a.ev(`return !document.getElementById("pesan").hidden`));
  check("pesan pendamping & sistem dimuat lewat fetch", await waitFor(a.ev, `document.querySelector(".chat-log").textContent.includes("UJI-PESAN-1") && document.querySelector(".bubble.system")`));
  await a.ev(`${fill("#msg", "Terima kasih kak. UJI-BALAS")} document.querySelector(".composer").requestSubmit()`);
  check("balasan penyintas tampil sebagai gelembung sendiri", await waitFor(a.ev, `[...document.querySelectorAll(".bubble.me")].some((b) => b.textContent.includes("UJI-BALAS"))`));
  check("kolom pesan dikosongkan setelah kirim", await a.ev(`return document.querySelector("#msg").value === ""`));
  php(`$r = Report::findByCode('${code}'); Message::create((int) $r['id'], 'companion', 2, 'Pesan baru lewat polling UJI-PESAN-2');`);
  check("pesan baru muncul otomatis dalam ≤ 6 detik (polling 5 detik)", await waitFor(a.ev, `document.querySelector(".chat-log").textContent.includes("UJI-PESAN-2")`, 7000));
  check("status sinkron diperbarui", await a.ev(`return document.querySelector("[data-sync]").textContent.includes("Diperbarui")`));

  // ---------- Companion: bell & thread ----------
  console.log("Pendamping: lonceng notifikasi");
  const b = await newPage("pendamping");
  await b.go("/masuk");
  await b.ev(`${fill("#email", "dewi@demo.id")} ${fill("#password", "haven123")} document.querySelector("form[data-validate]").requestSubmit()`);
  check("masuk sebagai pendamping", await waitFor(b.ev, `location.pathname === "/pendamping"`));
  check("lonceng menampilkan jumlah belum dibaca", await waitFor(b.ev, `document.querySelector("[data-notif-bell] .count")`));
  await b.ev(`document.querySelector("[data-notif-bell]").click()`);
  check("dropdown berisi notifikasi penugasan", await b.ev(`return !document.getElementById("notif-panel").hidden && document.querySelector("[data-notif-list]").textContent.includes("${code}")`));
  await b.go(`/pendamping/kasus/${code}#pesan`);
  check("pendamping melihat balasan penyintas", await waitFor(b.ev, `document.querySelector(".chat-log").textContent.includes("UJI-BALAS")`));
  check("pesan pendamping ditandai Dibaca setelah penyintas membuka", await waitFor(b.ev, `[...document.querySelectorAll(".bubble.me [data-read]")].some((s) => s.textContent === "Dibaca")`));
  await b.ev(`document.querySelector('[role="tab"][aria-controls="ringkasan"]').click()`);
  await b.ev(`${pick("status", "dirujuk")}`);
  check("memilih Dirujuk memunculkan pilihan layanan", await b.ev(`return !document.querySelector('[data-show-when="status=dirujuk"]').hidden`));
  await b.ev(`document.querySelector('form[action$="/status"]').requestSubmit()`);
  await sleep(200);
  check("validasi klien: layanan & persetujuan wajib", await b.ev(`return !!document.getElementById("svc-err") || !!document.getElementById("sepakat-err")`));

  // ---------- Directory ----------
  console.log("Direktori layanan");
  await a.go("/layanan");
  check("daftar awal dirender server", await a.ev(`return document.querySelectorAll("[data-directory-list] .service").length >= 10`));
  await a.ev(`{ const s = document.querySelector("#f-prov"); s.value = "DI Yogyakarta"; s.dispatchEvent(new Event("change", { bubbles: true })); }`);
  check("ganti provinsi memfilter lewat fetch", await waitFor(a.ev, `document.querySelector("[data-directory-list]").textContent.includes("DI Yogyakarta") && !document.querySelector("[data-directory-list]").textContent.includes("Banyumas")`));
  check("URL ikut diperbarui (bisa dibagikan)", await a.ev(`return location.search.includes("provinsi=DI")`));
  check("daftar kota ikut berganti", await a.ev(`return [...document.querySelectorAll("#f-kota option")].map((o) => o.textContent).join("|") === "Semua kota/kabupaten|Yogyakarta"`));
  await a.ev(`document.querySelector("[data-reset-filter]")?.click(); ${fill("#q", "zzz-tidak-ada")}`);
  check("hasil kosong menampilkan empty state", await waitFor(a.ev, `!document.querySelector("[data-empty]").hidden`));
  await a.go("/");
  check("beranda memuat 3 layanan lewat fetch", await waitFor(a.ev, `document.querySelectorAll("[data-directory-list] .service").length === 3`));

  // ---------- Admin charts ----------
  console.log("Grafik statistik");
  const adm = await newPage("admin");
  await adm.go("/masuk");
  await adm.ev(`${fill("#email", "admin@demo.id")} ${fill("#password", "haven123")} document.querySelector("form[data-validate]").requestSubmit()`);
  await waitFor(adm.ev, `location.pathname === "/admin"`);
  check("3 grafik dimuat dari /api/statistik", await waitFor(adm.ev, `[...document.querySelectorAll("canvas[data-chart-src]")].every((c) => c.dataset.chart && c.width > 0)`));
  check("label aksesibel grafik diperbarui dari data", await adm.ev(`return document.querySelector('canvas[data-chart-key="trend"]').getAttribute("aria-label").startsWith("Grafik garis laporan per bulan")`));

  // ---------- Quick exit ----------
  console.log("Keluar cepat");
  await a.go("/lacak");
  const before = a.requests.length;
  await a.ev(`for (let i = 0; i < 2; i++) document.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape", bubbles: true }))`);
  await sleep(800);
  check("Esc dua kali berpindah ke situs netral", a.requests.slice(before).some((u) => u.includes("google.com/search")));
  await a.go(`/kasus/${code}`);
  const before2 = a.requests.length;
  await a.ev(`document.querySelector("[data-quick-exit]").click()`);
  await sleep(800);
  check("tombol Keluar cepat berpindah ke situs netral", a.requests.slice(before2).some((u) => u.includes("google.com/search")));

  console.log("Konsol & CSP");
  check("tidak ada error JS atau pelanggaran CSP", problems.length === 0, "\n        " + problems.join("\n        "));
  // Licensed font files are supplied by the team (see public/assets/fonts/README.md); everything else must load.
  const fonts = [...missing].filter((u) => /\/assets\/fonts\/.+\.woff2$/.test(u));
  const other = [...missing].filter((u) => !fonts.includes(u));
  check("tidak ada aset/halaman yang gagal dimuat", other.length === 0, "\n        " + other.join("\n        "));
  if (fonts.length) console.log(`        (info: ${fonts.length} file font berlisensi belum dipasang, memakai fallback)`);
} catch (e) {
  fails++;
  console.log("  FAIL  " + e.message);
} finally {
  try { await send("Browser.close"); } catch {}
  edge.kill();
}
console.log(fails ? `\n${fails} cek gagal.` : "\nSemua cek lulus.");
process.exit(fails ? 1 : 0);
