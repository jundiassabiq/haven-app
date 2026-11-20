/* Haven: page behaviour. Progressive enhancement: every form also works without JS (server validates again). */
(() => {
  "use strict";
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const icon = (n) => `<svg class="icon" aria-hidden="true"><use href="#i-${n}"/></svg>`;
  /* Lite mode (Req 8.8): the server marks the page, and we drop every repeating
     request. What is left runs once, on demand, after the user asks for it. */
  const LITE = document.body.hasAttribute("data-lite");

  /* ---------- Quick exit: link, button, or Esc twice ---------- */
  const NEUTRAL = "https://www.google.com/search?q=prakiraan+cuaca+hari+ini";
  function quickExit() {
    try { sessionStorage.clear(); } catch (e) {}
    document.body.style.visibility = "hidden";
    window.location.replace(NEUTRAL);
  }
  let lastEsc = 0;
  document.addEventListener("keydown", (e) => {
    if (e.key !== "Escape") return;
    const t = Date.now();
    if (t - lastEsc < 600) quickExit();
    lastEsc = t;
  });
  /* The control is a link so it still works without JS. With JS we replace the
     current entry instead of navigating, so Back does not return to Haven. */
  document.addEventListener("click", (e) => {
    if (!e.target.closest("[data-quick-exit]")) return;
    e.preventDefault();
    quickExit();
  });
  window.HavenCsrf = () => document.querySelector('meta[name="csrf-token"]')?.content || "";
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));

  /* ---------- Toast ---------- */
  let region;
  function toast(msg, kind) {
    if (!region) {
      region = document.createElement("div");
      region.className = "toast-region";
      region.setAttribute("role", "status");
      region.setAttribute("aria-live", "polite");
      document.body.appendChild(region);
    }
    const t = document.createElement("div");
    t.className = "toast" + (kind === "error" ? " toast-error" : "");
    t.innerHTML = `${icon(kind === "error" ? "alert" : kind === "info" ? "info" : "check-circle")}<span>${esc(msg)}</span>`;
    region.appendChild(t);
    setTimeout(() => t.remove(), kind === "error" ? 7000 : 4200);
  }
  window.HavenToast = toast;
  $$("[data-toast]").forEach((b) => b.addEventListener("click", () => toast(b.dataset.toast)));
  /* Server flash messages */
  $$("[data-flash]").forEach((el) => toast(el.dataset.flash, el.dataset.flashKind));
  $$("[data-reload]").forEach((b) => b.addEventListener("click", () => location.reload()));
  $$("[data-back]").forEach((b) => b.addEventListener("click", () => (history.length > 1 ? history.back() : (location.href = "/"))));

  /* ---------- Dialogs opened from server-rendered buttons ---------- */
  $$("[data-open-dialog]").forEach((b) => b.addEventListener("click", () => document.getElementById(b.dataset.openDialog)?.showModal()));
  $$("[data-close-dialog]").forEach((b) => b.addEventListener("click", () => b.closest("dialog")?.close()));

  /* ---------- Missing image frames ---------- */
  $$(".media img").forEach((img) => {
    const f = img.closest(".media");
    const mark = () => f.classList.add("is-missing");
    if (img.complete && img.naturalWidth === 0) mark();
    img.addEventListener("error", mark, { once: true });
  });

  /* ---------- Public header ---------- */
  const header = $("[data-header]");
  if (header) {
    const onScroll = () => header.classList.toggle("is-scrolled", window.scrollY > 8);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
  }
  const toggle = $("[data-menu-toggle]");
  const mobileNav = $("[data-mobile-nav]");
  if (toggle && mobileNav) {
    const set = (open) => {
      toggle.setAttribute("aria-expanded", String(open));
      toggle.querySelector(".sr-only").textContent = open
        ? toggle.dataset.labelClose || "Tutup menu"
        : toggle.dataset.labelOpen || "Buka menu";
      mobileNav.hidden = !open;
      document.body.style.overflow = open ? "hidden" : "";
    };
    toggle.addEventListener("click", () => set(toggle.getAttribute("aria-expanded") !== "true"));
    mobileNav.addEventListener("click", (e) => { if (e.target.closest("a")) set(false); });
    window.matchMedia("(min-width: 961px)").addEventListener("change", (e) => { if (e.matches) set(false); });
  }

  /* ---------- App sidebar (off-canvas below 1024px) ---------- */
  const sbToggle = $("[data-sidebar-toggle]");
  const sidebar = $("[data-sidebar]");
  const scrim = $("[data-sidebar-scrim]");
  if (sbToggle && sidebar) {
    const mq = window.matchMedia("(max-width: 1024px)");
    const set = (open) => {
      sbToggle.setAttribute("aria-expanded", String(open));
      sbToggle.querySelector(".sr-only").textContent = open ? "Tutup navigasi" : "Buka navigasi";
      sidebar.classList.toggle("is-open", open);
      scrim.hidden = !open;
      if (mq.matches) sidebar.inert = !open; else sidebar.inert = false;
      if (open) sidebar.querySelector("a")?.focus();
    };
    set(false);
    sbToggle.addEventListener("click", () => set(!sidebar.classList.contains("is-open")));
    scrim.addEventListener("click", () => set(false));
    mq.addEventListener("change", () => set(false));
    document.addEventListener("keydown", (e) => { if (e.key === "Escape" && sidebar.classList.contains("is-open")) { set(false); sbToggle.focus(); } });
  }

  /* ---------- Dropdowns ---------- */
  $$("[data-dropdown]").forEach((dd) => {
    const btn = $("[data-dropdown-toggle]", dd);
    const panel = document.getElementById(btn.getAttribute("aria-controls"));
    const set = (open) => { btn.setAttribute("aria-expanded", String(open)); panel.hidden = !open; };
    btn.addEventListener("click", (e) => {
      e.stopPropagation();
      const open = panel.hidden;
      $$("[data-dropdown-toggle][aria-expanded='true']").forEach((b) => { b.setAttribute("aria-expanded", "false"); document.getElementById(b.getAttribute("aria-controls")).hidden = true; });
      set(open);
    });
    document.addEventListener("click", (e) => { if (!dd.contains(e.target)) set(false); });
    dd.addEventListener("keydown", (e) => { if (e.key === "Escape" && !panel.hidden) { e.stopPropagation(); set(false); btn.focus(); } });
  });
  $$("[data-mark-read]").forEach((b) => b.addEventListener("click", () => {
    const panel = b.closest(".dropdown-panel");
    $$(".is-unread", panel).forEach((a) => a.classList.remove("is-unread"));
    const count = b.closest(".dropdown").querySelector(".count");
    if (count) count.remove();
    toast("Semua notifikasi ditandai sudah dibaca.");
  }));

  /* ---------- Tabs (WAI-ARIA) ---------- */
  $$("[data-tabs]").forEach((root) => {
    const tabs = $$('[role="tab"]', root).filter((t) => t.closest("[data-tabs]") === root);
    const select = (tab, focus) => {
      tabs.forEach((t) => {
        const on = t === tab;
        t.setAttribute("aria-selected", String(on));
        t.tabIndex = on ? 0 : -1;
        document.getElementById(t.getAttribute("aria-controls")).hidden = !on;
      });
      if (focus) tab.focus();
      root.dispatchEvent(new CustomEvent("tabchange", { detail: tab }));
    };
    tabs.forEach((tab, i) => {
      tab.addEventListener("click", () => select(tab, false));
      tab.addEventListener("keydown", (e) => {
        const map = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 };
        if (!(e.key in map)) return;
        e.preventDefault();
        select(tabs[(map[e.key] + tabs.length) % tabs.length], true);
      });
    });
    const hash = location.hash.slice(1);
    const fromHash = hash && tabs.find((t) => t.getAttribute("aria-controls") === hash);
    if (fromHash) select(fromHash, false);
  });
  $$("[data-open-tab]").forEach((a) => a.addEventListener("click", (e) => {
    const tab = $(`[role="tab"][aria-controls="${a.dataset.openTab}"]`);
    if (tab) { e.preventDefault(); tab.click(); tab.scrollIntoView({ block: "start", behavior: reduce ? "auto" : "smooth" }); }
  }));

  /* ---------- Counters ---------- */
  const counters = $$("[data-count]");
  if (counters.length && !reduce && "IntersectionObserver" in window) {
    const io = new IntersectionObserver((entries) => entries.forEach((en) => {
      if (!en.isIntersecting) return;
      io.unobserve(en.target);
      const el = en.target, target = Number(el.dataset.count), start = performance.now();
      const tick = (now) => {
        const p = Math.min((now - start) / 1800, 1);
        el.textContent = Math.round(target * (p === 1 ? 1 : 1 - Math.pow(2, -10 * p))).toLocaleString("id-ID");
        if (p < 1) requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    }), { threshold: 0.6 });
    counters.forEach((c) => io.observe(c));
  }

  /* ---------- Copy & download ---------- */
  $$("[data-copy]").forEach((b) => b.addEventListener("click", async () => {
    const text = b.dataset.copy;
    try { await navigator.clipboard.writeText(text); } catch (e) {
      const ta = Object.assign(document.createElement("textarea"), { value: text });
      document.body.appendChild(ta); ta.select(); document.execCommand("copy"); ta.remove();
    }
    const label = b.querySelector("span");
    const old = label.textContent;
    label.textContent = "Tersalin";
    setTimeout(() => (label.textContent = old), 1800);
  }));
  $$("[data-download]").forEach((b) => b.addEventListener("click", () => {
    const blob = new Blob([b.dataset.download.replace(/\\n/g, "\n")], { type: "text/plain" });
    const a = Object.assign(document.createElement("a"), { href: URL.createObjectURL(blob), download: b.dataset.filename || "catatan.txt" });
    a.click();
    URL.revokeObjectURL(a.href);
  }));

  /* ---------- Password reveal ---------- */
  $$("[data-reveal]").forEach((b) => b.addEventListener("click", () => {
    const input = document.getElementById(b.dataset.reveal);
    const show = input.type === "password";
    input.type = show ? "text" : "password";
    b.setAttribute("aria-pressed", String(show));
    b.innerHTML = `${icon(show ? "eye-off" : "eye")}<span class="sr-only">${show ? "Sembunyikan" : "Tampilkan"}</span>`;
  }));

  /* ---------- Uppercase code input ---------- */
  $$("[data-uppercase]").forEach((i) => i.addEventListener("input", () => {
    const p = i.selectionStart;
    i.value = i.value.toUpperCase().replace(/\s/g, "");
    i.setSelectionRange(p, p);
  }));
  $$("[data-digits]").forEach((i) => i.addEventListener("input", () => { i.value = i.value.replace(/\D/g, "").slice(0, Number(i.maxLength) || 6); }));

  /* ---------- Character counters ---------- */
  $$("textarea[maxlength][data-counter]").forEach((ta) => {
    const out = document.getElementById(ta.dataset.counter);
    const max = Number(ta.maxLength);
    const upd = () => {
      out.textContent = `${ta.value.length.toLocaleString("id-ID")} / ${max.toLocaleString("id-ID")}`;
      out.classList.toggle("is-near", ta.value.length > max * 0.9);
    };
    ta.addEventListener("input", upd); upd();
  });

  /* ---------- Conditional fields: data-show-when="name=value" ---------- */
  const conds = $$("[data-show-when]");
  const evalConds = () => conds.forEach((el) => {
    const [name, val] = el.dataset.showWhen.split("=");
    const on = $$(`[name="${name}"]`).some((i) => ((i.type === "radio" || i.type === "checkbox") ? i.checked : true) && i.value === val);
    el.hidden = !on;
    $$("input, select, textarea", el).forEach((i) => { if (i.dataset.condRequired !== undefined) i.required = on; });
  });
  if (conds.length) { document.addEventListener("change", evalConds); evalConds(); }

  /* ---------- File drop ---------- */
  const fmtSize = (b) => (b > 1048576 ? (b / 1048576).toFixed(1) + " MB" : Math.max(1, Math.round(b / 1024)) + " KB");
  $$("[data-dropzone]").forEach((dz) => {
    const input = $("input[type=file]", dz);
    const list = document.getElementById(dz.dataset.dropzone);
    const ok = ["image/jpeg", "image/png", "application/pdf"];
    const render = () => {
      list.innerHTML = [...input.files].map((f) => {
        const bad = !ok.includes(f.type) ? "Format tidak didukung" : f.size > 5 * 1048576 ? "Lebih dari 5 MB" : "";
        return `<li class="${bad ? "is-bad" : ""}">${icon(bad ? "alert" : "paperclip")}<span class="name">${esc(f.name)}</span><span class="size">${bad || fmtSize(f.size)}</span></li>`;
      }).join("");
    };
    input.addEventListener("change", render);
    ["dragenter", "dragover"].forEach((ev) => dz.addEventListener(ev, () => dz.classList.add("is-over")));
    ["dragleave", "drop"].forEach((ev) => dz.addEventListener(ev, () => dz.classList.remove("is-over")));
  });

  /* ---------- Validation ---------- */
  function setError(el, msg) {
    const holder = el.type === "radio" || el.type === "checkbox" && el.closest("fieldset") ? el.closest("fieldset") || el.closest(".field") : el.closest(".field") || el.parentElement;
    const id = (el.id || el.name) + "-err";
    let err = document.getElementById(id);
    const group = el.type === "radio" ? $$(`[name="${el.name}"]`, el.form || document) : [el];
    if (!msg) {
      group.forEach((g) => g.removeAttribute("aria-invalid"));
      if (err) err.remove();
      return;
    }
    group.forEach((g) => { g.setAttribute("aria-invalid", "true"); g.setAttribute("aria-describedby", id); });
    if (!err) {
      err = document.createElement("p");
      err.className = "error";
      err.id = id;
      holder.appendChild(err);
    }
    err.innerHTML = `${icon("alert")}<span>${esc(msg)}</span>`;
    err.querySelector(".icon").style.cssText = "width:16px;height:16px;margin-top:2px";
  }
  function checkField(el) {
    if (el.disabled || el.closest("[hidden]")) { setError(el, ""); return true; }
    const v = el.type === "checkbox" ? el.checked : el.value.trim();
    let msg = "";
    if (el.type === "radio") {
      if (el.required && !$$(`[name="${el.name}"]`, el.form || document).some((r) => r.checked)) msg = el.dataset.msg || "Pilih salah satu.";
    } else if (el.required && !v) msg = el.dataset.msg || (el.type === "checkbox" ? "Centang untuk melanjutkan." : "Bagian ini wajib diisi.");
    else if (el.type === "email" && v && !el.checkValidity()) msg = "Format email belum benar. Contoh: nama@email.com";
    else if (el.minLength > 0 && v && el.value.trim().length < el.minLength) msg = `Tulis minimal ${el.minLength} karakter. Saat ini ${el.value.trim().length}.`;
    else if (el.pattern && v && !new RegExp(`^(?:${el.pattern})$`).test(el.value)) msg = el.dataset.msgPattern || "Formatnya belum sesuai.";
    else if (el.dataset.match && v !== document.getElementById(el.dataset.match).value) msg = "Kata sandi tidak sama dengan yang di atas.";
    setError(el, msg);
    return !msg;
  }
  function validate(scope) {
    const seen = new Set();
    let first = null;
    $$("input, select, textarea", scope).forEach((el) => {
      if (el.type === "file" || el.type === "hidden" || el.type === "submit") return;
      if (el.type === "radio") { if (seen.has(el.name)) return; seen.add(el.name); }
      if (!checkField(el) && !first) first = el;
    });
    if (first) first.focus();
    return !first;
  }
  document.addEventListener("change", (e) => { if (e.target.matches("[aria-invalid]")) checkField(e.target); });
  document.addEventListener("input", (e) => { if (e.target.matches("[aria-invalid]:not([type=radio])")) checkField(e.target); });

  /* Real forms: validate in the browser, then submit normally. The server validates again. */
  $$("form[data-validate]").forEach((form) => form.addEventListener("submit", (e) => {
    if (!validate(form)) { e.preventDefault(); return; }
    const btn = e.submitter || $("[type=submit]", form);
    if (btn) { btn.setAttribute("aria-busy", "true"); setTimeout(() => (btn.disabled = true), 0); }
  }));

  /* Demo forms: validate, then redirect or toast */
  $$("form[data-demo]").forEach((form) => form.addEventListener("submit", (e) => {
    e.preventDefault();
    if (!validate(form)) return;
    const btn = $("[type=submit]", form);
    const go = () => {
      if (form.dataset.redirect) { location.href = form.dataset.redirect; return; }
      if (btn) { btn.removeAttribute("aria-busy"); btn.disabled = false; }
      if (form.dataset.successShow) {
        form.hidden = true;
        document.getElementById(form.dataset.successShow).hidden = false;
      }
      if (form.dataset.success) toast(form.dataset.success);
      if (form.dataset.reset !== undefined) form.reset();
    };
    if (btn) { btn.setAttribute("aria-busy", "true"); btn.disabled = true; }
    setTimeout(go, 600);
  }));

  /* ---------- Stepper (lapor) ---------- */
  $$("form[data-stepper]").forEach((form) => {
    const steps = $$("[data-step]", form);
    const items = $$(".stepper li", form);
    const prev = $("[data-prev]", form), next = $("[data-next]", form), submit = $("[data-submit]", form);
    const count = $("[data-step-count]", form);
    let i = 0;
    const fill = () => $$("[data-summary]", form).forEach((el) => {
      const inputs = $$(`[name="${el.dataset.summary}"]`, form);
      const vals = inputs.filter((x) => (x.type === "radio" || x.type === "checkbox") ? x.checked : x.value.trim()).map((x) => {
        const box = x.closest(".choice, .check");
        const t = box && (box.querySelector(".choice-title") || box.querySelector("span"));
        if (t) return t.textContent.trim();
        if (x.tagName === "SELECT") return x.options[x.selectedIndex].text;
        if (x.type === "date" && x.value) return new Date(x.value).toLocaleDateString("id-ID", { day: "numeric", month: "long", year: "numeric" });
        return x.value.length > 160 ? x.value.slice(0, 160) + "…" : x.value;
      });
      el.textContent = vals.join(", ") || "Tidak diisi";
    });
    const show = (n, focus = true) => {
      i = n;
      steps.forEach((s, k) => (s.hidden = k !== n));
      items.forEach((li, k) => {
        li.classList.toggle("is-done", k < n);
        if (k === n) li.setAttribute("aria-current", "step"); else li.removeAttribute("aria-current");
      });
      count.textContent = `Langkah ${n + 1} dari ${steps.length}`;
      prev.hidden = n === 0;
      next.hidden = n === steps.length - 1;
      submit.hidden = n !== steps.length - 1;
      if (n === steps.length - 1) fill();
      if (focus) {
        const h = $(".step-title", steps[n]);
        h.setAttribute("tabindex", "-1");
        h.focus({ preventScroll: true });
        form.scrollIntoView({ block: "start", behavior: reduce ? "auto" : "smooth" });
      }
    };
    next.addEventListener("click", () => { if (validate(steps[i])) show(i + 1); });
    prev.addEventListener("click", () => show(i - 1));
    form.addEventListener("submit", (e) => {
      if (!validate(steps[i])) { e.preventDefault(); return; }
      submit.setAttribute("aria-busy", "true");
      submit.querySelector("span").textContent = "Mengirim dengan aman…";
      setTimeout(() => (submit.disabled = true), 0);
    });
    // After a server-side validation error, open the first step that has an invalid field.
    const firstBad = steps.findIndex((s) => s.querySelector('[aria-invalid="true"]'));
    show(firstBad > 0 ? firstBad : 0, false);
    if (firstBad >= 0) steps[firstBad].querySelector('[aria-invalid="true"]')?.focus();
  });

  /* ---------- Filter forms that submit on change (server-side filters + pagination) ---------- */
  $$("form[data-autosubmit]").forEach((f) => {
    f.addEventListener("change", (e) => { if (e.target.matches("select, input[type=checkbox]")) f.requestSubmit(); });
  });

  /* ---------- Filtered lists (edukasi, tabel admin) ---------- */
  $$("[data-filter-list]").forEach((root) => {
    const items = $$("[data-item]", root);
    const chips = $$("[data-filter]", root);
    const q = $("[data-search]", root);
    const selects = $$("[data-filter-select]", root);
    const empty = $("[data-empty]", root);
    const count = $("[data-result-count]", root);
    let cat = "semua";
    const apply = () => {
      const t = (q?.value || "").trim().toLowerCase();
      let n = 0;
      items.forEach((it) => {
        let ok = (cat === "semua" || it.dataset.category === cat) && (!t || it.textContent.toLowerCase().includes(t));
        selects.forEach((s) => { if (s.value && it.dataset[s.dataset.filterSelect] !== s.value) ok = false; });
        it.hidden = !ok;
        n += ok;
      });
      if (empty) empty.hidden = n > 0;
      if (count) count.textContent = `${n} ${count.dataset.noun || "hasil"}`;
    };
    chips.forEach((c) => c.addEventListener("click", () => {
      chips.forEach((x) => x.setAttribute("aria-pressed", String(x === c)));
      cat = c.dataset.filter;
      apply();
    }));
    q?.addEventListener("input", apply);
    selects.forEach((s) => s.addEventListener("change", apply));
    $$("[data-reset-filter]", root).forEach((b) => b.addEventListener("click", () => {
      if (q) q.value = "";
      selects.forEach((s) => (s.value = ""));
      chips[0]?.click();
      apply();
    }));
    apply();
  });

  /* ---------- Services directory: filters call /api/layanan; the server also renders the first list for no-JS ---------- */
  const TYPE_LABEL = { hotline: "Hotline", uptd: "UPTD PPA", polisi: "Kepolisian (Unit PPA)", rs: "Rumah sakit", lbh: "Lembaga bantuan hukum", psikolog: "Psikolog", kampus: "Satgas kampus" };
  const serviceItem = (s) => `
    <li class="service">
      <div class="service-title"><h3>${esc(s.name)}</h3>${s.verified ? '<span class="badge badge-ok">Terverifikasi</span>' : '<span class="badge badge-verify">Perlu verifikasi</span>'}</div>
      <div class="service-meta">
        <span>${icon("building")}${esc(TYPE_LABEL[s.type] || s.type)}</span>
        <span>${icon("pin")}${esc(s.area)}</span>
        <span>${icon("clock")}${esc(s.hours || "Jam layanan belum dicatat")}</span>
      </div>
      <div class="service-tags">${s.tags.map((x) => `<span class="badge badge-plain">${esc(x)}</span>`).join("")}</div>
      <div class="service-actions">
        ${s.phone ? `<a class="btn btn-ink btn-xs" href="tel:${esc(s.phone.replace(/[^0-9+]/g, ""))}">${icon("phone")} Telepon <span class="mono">${esc(s.phone)}</span></a>` : ""}
        ${s.whatsapp ? `<a class="btn btn-ghost btn-xs" href="https://wa.me/${esc(s.whatsapp.replace(/[^0-9]/g, "").replace(/^0/, "62"))}" rel="noopener noreferrer">${icon("message")} <span class="mono">${esc(s.whatsapp)}</span></a>` : ""}
        ${s.contact_label ? `<span class="small muted"><strong>${esc(s.contact_label)}</strong> · nomor belum tersedia</span>` : s.verified_at ? `<span class="small muted">Nomor dicek ${esc(s.verified_at)}</span>` : ""}
      </div>
    </li>`;
  $$("[data-directory]").forEach((root) => {
    const list = $("[data-directory-list]", root);
    const form = $("form", root);
    const q = $("[name=q]", root), prov = $("[name=provinsi]", root), city = $("[name=kota]", root), type = $("[name=jenis]", root), h24 = $("[name=h24]", root);
    const empty = $("[data-empty]", root), count = $("[data-result-count]", root);
    const limit = Number(root.dataset.limit) || 0;
    let ctrl, timer;
    const params = () => {
      const p = new URLSearchParams();
      if (q?.value.trim()) p.set("q", q.value.trim());
      if (prov?.value) p.set("provinsi", prov.value);
      if (city?.value) p.set("kota", city.value);
      if (type?.value) p.set("jenis", type.value);
      if (h24?.checked) p.set("h24", "1");
      return p;
    };
    const load = async () => {
      const p = params();
      if (limit) p.set("limit", String(limit));
      ctrl?.abort();
      ctrl = new AbortController();
      list.setAttribute("aria-busy", "true");
      try {
        const r = await fetch(`${root.dataset.endpoint}?${p}`, { headers: { Accept: "application/json" }, signal: ctrl.signal });
        if (!r.ok) throw new Error(String(r.status));
        const d = await r.json();
        list.innerHTML = d.services.map(serviceItem).join("");
        if (city && d.cities) {
          const cur = city.value;
          city.innerHTML = '<option value="">Semua kota/kabupaten</option>' + d.cities.map((c) => `<option${c === cur ? " selected" : ""}>${esc(c)}</option>`).join("");
        }
        if (empty) empty.hidden = d.services.length > 0;
        if (count) count.textContent = `${d.total} layanan`;
        if (!limit) { p.delete("limit"); history.replaceState(null, "", p.toString() ? `?${p}` : location.pathname); }
      } catch (e) {
        if (e.name !== "AbortError") list.innerHTML = '<li class="alert alert-danger">Daftar layanan gagal dimuat. Periksa koneksi internetmu, lalu ubah filter untuk mencoba lagi.</li>';
      } finally {
        list.removeAttribute("aria-busy");
      }
    };
    form?.addEventListener("submit", (e) => { e.preventDefault(); load(); });
    q?.addEventListener("input", () => { clearTimeout(timer); timer = setTimeout(load, 250); });
    [type, city, h24].forEach((el) => el?.addEventListener("change", load));
    prov?.addEventListener("change", () => { if (city) city.value = ""; load(); });
    $$("[data-reset-filter]", root).forEach((b) => b.addEventListener("click", () => {
      [q, prov, city, type].forEach((el) => el && (el.value = ""));
      if (h24) h24.checked = false;
      load();
    }));
    if (!list.children.length) load();
  });

  /* ---------- Secure chat: polls every 5 s while the tab is visible ---------- */
  $$("[data-chat]").forEach((chat) => {
    const endpoint = chat.dataset.endpoint;
    const log = $(".chat-log", chat), form = $(".composer", chat), sync = $("[data-sync]", chat), err = $("[data-chat-error]", chat);
    const ta = form ? $("textarea", form) : null;
    const syncText = (t) => { if (sync) sync.lastChild.textContent = " " + t; };
    let last = 0, lastDay = null, busy = false, stopped = false;
    const scroll = () => (log.scrollTop = log.scrollHeight);
    const nearBottom = () => log.scrollHeight - log.scrollTop - log.clientHeight < 80;
    const render = (m) => {
      if (m.day !== lastDay) { log.insertAdjacentHTML("beforeend", `<span class="day-sep">${esc(m.day)}</span>`); lastDay = m.day; }
      if (m.system) { log.insertAdjacentHTML("beforeend", `<div class="bubble system">${esc(m.body)}</div>`); return; }
      const meta = m.mine ? `${esc(m.time)} · <span data-read>${m.read ? "Dibaca" : "Terkirim"}</span>` : `${esc(m.time)} · ${esc(m.sender)}`;
      log.insertAdjacentHTML("beforeend", `<div class="bubble${m.mine ? " me" : ""}" data-id="${m.id}">${esc(m.body).replace(/\n/g, "<br>")}<span class="bubble-meta">${meta}</span></div>`);
    };
    const poll = async () => {
      if (busy || stopped) return;
      busy = true;
      try {
        const r = await fetch(`${endpoint}?after=${last}`, { headers: { Accept: "application/json", "X-Requested-With": "fetch" } });
        if (r.status === 401 || r.status === 403) { stopped = true; syncText("Sesi berakhir. Muat ulang halaman."); return; }
        if (!r.ok) throw new Error(String(r.status));
        const d = await r.json();
        const stick = last === 0 || nearBottom();
        if (last === 0) log.innerHTML = "";
        d.messages.forEach(render);
        if (d.messages.length) last = d.messages[d.messages.length - 1].id;
        if (last === 0 && !log.children.length) log.innerHTML = '<p class="bubble system">Belum ada pesan.</p>';
        $$(".bubble.me[data-id]", log).forEach((b) => { if (Number(b.dataset.id) <= d.read_upto) b.querySelector("[data-read]").textContent = "Dibaca"; });
        if (stick && d.messages.length) scroll();
        syncText("Diperbarui " + new Date().toLocaleTimeString("id-ID", { hour: "2-digit", minute: "2-digit" }));
      } catch (e) {
        syncText("Gagal memperbarui, mencoba lagi…");
      } finally {
        busy = false;
      }
    };
    poll();
    if (LITE) {
      /* No interval in lite mode (Req 8.8). The survivor pulls new messages when
         they want them, so the page makes one request per tap instead of one
         every five seconds. */
      if (sync) {
        const refresh = document.createElement("button");
        refresh.type = "button";
        refresh.className = "btn btn-ghost btn-xs";
        refresh.textContent = "Muat pesan baru";
        refresh.addEventListener("click", () => poll());
        sync.insertAdjacentElement("afterend", refresh);
      }
    } else {
      setInterval(() => { if (document.hidden) syncText("Dijeda"); else poll(); }, 5000);
      document.addEventListener("visibilitychange", () => { if (!document.hidden) poll(); });
    }

    if (!form) return;
    const grow = () => { ta.style.height = "auto"; ta.style.height = Math.min(ta.scrollHeight, 160) + "px"; };
    ta.addEventListener("input", grow);
    ta.addEventListener("keydown", (e) => { if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const text = ta.value.trim();
      if (!text) { ta.focus(); return; }
      const btn = $("[type=submit]", form);
      btn.disabled = true;
      if (err) err.hidden = true;
      try {
        const r = await fetch(endpoint, {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json", "X-Requested-With": "fetch", "X-CSRF-Token": window.HavenCsrf() },
          body: JSON.stringify({ body: text }),
        });
        const d = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(d.error || "Pesan belum terkirim. Coba lagi.");
        ta.value = ""; grow();
        await poll();
        scroll();
      } catch (ex) {
        if (err) { err.textContent = ex.message; err.hidden = false; }
      } finally {
        btn.disabled = false;
        ta.focus();
      }
    });
  });

  /* ---------- Slot booking: enable the submit button once a slot is picked ---------- */
  $$("[data-slots]").forEach((root) => {
    const btn = $("[data-book]", root);
    root.addEventListener("change", (e) => { if (e.target.name === "slot_id" && btn) btn.disabled = false; });
  });

  /* ---------- Confirm dialogs (destructive actions only) ---------- */
  $$("[data-confirm]").forEach((trigger) => trigger.addEventListener("click", (e) => {
    e.preventDefault();
    const dlg = document.getElementById(trigger.dataset.confirm);
    if (!dlg) return;
    dlg.returnValue = "";
    dlg.showModal();
    dlg.addEventListener("close", function onClose() {
      dlg.removeEventListener("close", onClose);
      if (dlg.returnValue !== "ok") return;
      if (trigger.dataset.done) toast(trigger.dataset.done);
      if (trigger.dataset.removeClosest) trigger.closest(trigger.dataset.removeClosest)?.remove();
      if (trigger.dataset.href) location.href = trigger.dataset.href;
    });
  }));
  $$("dialog form[method=dialog]").forEach((f) => f.addEventListener("submit", (e) => {
    const sub = e.submitter;
    if (sub && sub.value === "ok" && !validate(f)) { e.preventDefault(); }
  }));

  /* ---------- Notification bell (signed-in users): count + latest 5, refreshed every 30 s ---------- */
  const bell = $("[data-notif-bell]");
  if (bell) {
    const list = $("[data-notif-list]");
    const count = () => bell.querySelector(".count");
    const render = (d) => {
      count()?.remove();
      bell.querySelector(".sr-only").textContent = d.unread ? `Notifikasi, ${d.unread} belum dibaca` : "Notifikasi";
      if (d.unread) bell.insertAdjacentHTML("beforeend", `<span class="count" aria-hidden="true">${d.unread > 99 ? "99+" : d.unread}</span>`);
      list.innerHTML = d.items.length
        ? d.items.map((n) => `<li><a href="${esc(n.url)}" class="${n.unread ? "is-unread" : ""}"><span class="notif-dot" aria-hidden="true"></span><span><span class="notif-title">${esc(n.title)}${n.unread ? '<span class="sr-only"> (belum dibaca)</span>' : ""}</span><span class="notif-meta">${esc(n.meta)}</span></span></a></li>`).join("")
        : '<li><p class="small muted" style="padding:18px">Belum ada notifikasi.</p></li>';
    };
    const load = async () => {
      try {
        const r = await fetch(bell.dataset.endpoint, { headers: { Accept: "application/json", "X-Requested-With": "fetch" } });
        if (r.ok) render(await r.json());
      } catch (e) { /* keep the last state; next poll retries */ }
    };
    load();
    // Lite mode counts the badge once per page load, with no refresh loop (Req 8.8).
    if (!LITE) setInterval(() => { if (!document.hidden) load(); }, 30000);
    $$("[data-mark-read]").forEach((b) => b.addEventListener("click", async () => {
      await fetch(b.dataset.endpoint, { method: "POST", headers: { "X-CSRF-Token": window.HavenCsrf(), "X-Requested-With": "fetch", Accept: "application/json" } });
      await load();
      toast("Semua notifikasi ditandai sudah dibaca.");
    }));
  }

  /* ---------- Charts (canvas, vanilla) ---------- */
  const CSS = getComputedStyle(document.documentElement);
  const C = { ink: "#1C1B19", ink3: "#6A655C", line: "#E4DFD3", yellow: "#FFC907", deep: "#C99400" };
  const PALETTE = [C.ink, C.yellow, "#8C8579", "#C9C2B3"];
  function niceMax(v) { const p = Math.pow(10, Math.floor(Math.log10(v || 1))); return Math.ceil(v / p / (v / p > 5 ? 2 : 1)) * p * (v / p > 5 ? 2 : 1); }
  function draw(canvas) {
    const cfg = JSON.parse(canvas.dataset.chart);
    const box = canvas.parentElement;
    const w = box.clientWidth, h = box.clientHeight, dpr = window.devicePixelRatio || 1;
    canvas.width = w * dpr; canvas.height = h * dpr;
    const ctx = canvas.getContext("2d");
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, w, h);
    const mono = "11px " + (CSS.getPropertyValue("--font-mono") || "monospace");
    const sans = "12px " + (CSS.getPropertyValue("--font-sans") || "sans-serif");
    const series = cfg.series;
    const all = series.flatMap((s) => s.values);
    if (!all.length || all.every((v) => !v)) {
      ctx.font = sans; ctx.fillStyle = C.ink3; ctx.textAlign = "center"; ctx.textBaseline = "middle";
      ctx.fillText("Belum ada data untuk rentang ini", w / 2, h / 2);
      return;
    }
    const max = niceMax(Math.max(...all));

    if (cfg.type === "hbar") {
      ctx.font = sans;
      const lw = Math.min(w * 0.42, Math.max(...cfg.labels.map((l) => ctx.measureText(l).width)) + 16);
      const fit = (t) => { if (ctx.measureText(t).width <= lw - 12) return t; while (t.length > 1 && ctx.measureText(t + "…").width > lw - 12) t = t.slice(0, -1); return t + "…"; };
      const rowH = Math.min(36, (h - 8) / cfg.labels.length);
      cfg.labels.forEach((l, i) => {
        const y = 4 + i * rowH, v = series[0].values[i], bw = ((w - lw - 48) * v) / max;
        ctx.fillStyle = C.ink; ctx.font = sans; ctx.textBaseline = "middle"; ctx.textAlign = "left";
        ctx.fillText(fit(l), 0, y + rowH / 2);
        ctx.fillStyle = "#F1EDE4"; ctx.fillRect(lw, y + rowH * 0.2, w - lw - 48, rowH * 0.6);
        ctx.fillStyle = i === 0 ? C.ink : C.yellow; ctx.fillRect(lw, y + rowH * 0.2, Math.max(2, bw), rowH * 0.6);
        ctx.fillStyle = C.ink; ctx.font = "bold " + mono; ctx.fillText(String(v), lw + bw + 8, y + rowH / 2);
      });
      return;
    }

    const pad = { l: 36, r: 10, t: 12, b: 28 };
    const pw = w - pad.l - pad.r, ph = h - pad.t - pad.b;
    ctx.font = mono; ctx.fillStyle = C.ink3; ctx.textAlign = "right"; ctx.textBaseline = "middle";
    for (let k = 0; k <= 4; k++) {
      const y = pad.t + ph - (ph * k) / 4;
      ctx.strokeStyle = C.line; ctx.lineWidth = 1;
      ctx.beginPath(); ctx.moveTo(pad.l, Math.round(y) + 0.5); ctx.lineTo(w - pad.r, Math.round(y) + 0.5); ctx.stroke();
      ctx.fillText(String(Math.round((max * k) / 4)), pad.l - 8, y);
    }
    const n = cfg.labels.length, step = pw / n;
    ctx.textAlign = "center"; ctx.textBaseline = "top";
    const every = Math.ceil(n / Math.max(1, Math.floor(pw / 44)));
    cfg.labels.forEach((l, i) => { if (i % every === 0) ctx.fillText(l, pad.l + step * i + step / 2, h - pad.b + 10); });

    if (cfg.type === "bar") {
      const gw = step * 0.64, bw = gw / series.length;
      series.forEach((s, si) => s.values.forEach((v, i) => {
        const bh = (ph * v) / max, x = pad.l + step * i + (step - gw) / 2 + bw * si;
        ctx.fillStyle = PALETTE[si];
        ctx.fillRect(x + 1, pad.t + ph - bh, bw - 2, bh);
      }));
    } else {
      series.forEach((s, si) => {
        const pts = s.values.map((v, i) => [pad.l + step * i + step / 2, pad.t + ph - (ph * v) / max]);
        if (si === 0) {
          ctx.beginPath(); ctx.moveTo(pts[0][0], pad.t + ph);
          pts.forEach((p) => ctx.lineTo(p[0], p[1]));
          ctx.lineTo(pts[pts.length - 1][0], pad.t + ph); ctx.closePath();
          ctx.fillStyle = "rgba(255, 201, 7, 0.28)"; ctx.fill();
        }
        ctx.beginPath(); pts.forEach((p, i) => (i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1])));
        ctx.strokeStyle = PALETTE[si]; ctx.lineWidth = 2.5; ctx.lineJoin = "round"; ctx.stroke();
        pts.forEach((p) => { ctx.beginPath(); ctx.arc(p[0], p[1], 3.5, 0, Math.PI * 2); ctx.fillStyle = "#fff"; ctx.fill(); ctx.lineWidth = 2; ctx.stroke(); });
      });
    }
  }
  const charts = $$("canvas[data-chart], canvas[data-chart-src]");
  if (charts.length) {
    const redraw = () => charts.filter((c) => c.dataset.chart).forEach(draw);
    // Canvases with data-chart-src share one fetch per URL; data-chart-key picks their series.
    const srcs = [...new Set(charts.map((c) => c.dataset.chartSrc).filter(Boolean))];
    Promise.all(srcs.map((src) => fetch(src, { headers: { Accept: "application/json", "X-Requested-With": "fetch" } })
      .then((r) => (r.ok ? r.json() : Promise.reject(r.status)))
      .then((data) => charts.filter((c) => c.dataset.chartSrc === src).forEach((c) => {
        const cfg = data[c.dataset.chartKey];
        if (!cfg) return;
        c.dataset.chart = JSON.stringify(cfg);
        if (cfg.label) c.setAttribute("aria-label", cfg.label);
      }))
      .catch(() => charts.filter((c) => c.dataset.chartSrc === src).forEach((c) => c.insertAdjacentHTML("afterend", '<p class="small muted">Grafik gagal dimuat. Muat ulang halaman.</p>')))))
      .then(() => (document.fonts ? document.fonts.ready : null))
      .then(redraw);
    if ("ResizeObserver" in window) { const ro = new ResizeObserver(() => redraw()); charts.forEach((c) => ro.observe(c.parentElement)); }
  }
})();
