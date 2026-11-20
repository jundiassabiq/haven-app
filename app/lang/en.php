<?php
declare(strict_types=1);

/*
 * English dictionary for the static interface (Req 8.5).
 *
 * Keys mirror `app/lang/id.php`. A key that is missing or empty here falls back
 * to the Bahasa Indonesia text through `I18n::t` (Req 8.7), so an untranslated
 * string never leaves a blank on the page.
 *
 * Emergency numbers, legal institution names (SAPA 129, KPAI, UPTD PPA), and
 * anything written by survivors, companions, or admins stay as they are.
 *
 * Placeholders `:name` are filled by
 * `I18n::t($key, $lang, $dict, ['name' => ...])`.
 *
 * @return array<string,string>
 */

return [
    // ---------- Accessibility and page frame (Req 8.2) ----------
    'a11y.skip' => 'Skip to content',
    'a11y.menu.open' => 'Open menu',
    'a11y.menu.close' => 'Close menu',
    'a11y.nav.primary' => 'Main',
    'a11y.nav.mobile' => 'Mobile menu',
    'a11y.home' => 'Haven, back to home',
    'a11y.reveal' => 'Show',
    'a11y.hide' => 'Hide',
    'a11y.required' => 'required',

    // ---------- Navigation and header ----------
    'nav.report' => 'Report',
    'nav.track' => 'Track a report',
    'nav.edu' => 'Learn',
    'nav.services' => 'Support services',
    'nav.about' => 'About',
    'nav.account' => 'My account',
    'nav.login' => 'Sign in',
    'nav.register' => 'Create account',
    'nav.cta' => 'File a report',

    // ---------- Language and mode switch (Req 8.6, 8.13) ----------
    'lang.label' => 'Language',
    'lang.switch' => 'Change language',
    'lang.id' => 'Bahasa Indonesia',
    'lang.en' => 'English',
    'mode.label' => 'Display',
    'mode.full' => 'Full',
    'mode.lite' => 'Lite',
    'mode.switch.to_lite' => 'Switch to lite mode',
    'mode.switch.to_full' => 'Switch to full mode',
    'mode.lite.hint' => 'Lite mode loads pages without images and without auto-refresh, so it saves data and still works on a weak signal.',
    'mode.lite.auto' => 'Lite mode is on because your device asked to save data. You can change it here.',
    'prefs.active' => 'in use',

    // ---------- Emergency calls (Req 8.12, 8.14) ----------
    'emergency.title' => 'In danger right now?',
    'emergency.lede' => 'Call a number below. These lines are free and open 24 hours.',
    'emergency.call' => 'Call :nomor',
    'emergency.112.label' => 'National emergency line',
    'emergency.129.label' => 'SAPA 129 (Ministry of Women Empowerment and Child Protection)',

    // ---------- Service limits (Req 9.5, 9.6) ----------
    // Filled by `service_limit_html()`: :biasa and :bahaya are the reply deadlines
    // from the SLA configuration.
    'service.limit' => 'Haven is not an emergency service. Reports are reviewed during office hours and answered within :biasa. Reports marked as in danger come first and are answered within :bahaya. If you are in danger right now, call <a href="tel:112">112</a> or SAPA <a href="tel:129">129</a>.',

    // ---------- Quick exit ----------
    'exit.button' => 'Quick exit',
    'exit.hint' => 'Press Esc twice to switch to a weather page.',

    // ---------- Footer ----------
    'footer.services' => 'Services',
    'footer.learn' => 'Learn',
    'footer.about' => 'About',
    'footer.privacy' => 'Confidentiality',
    'footer.directory' => 'Service directory',
    'footer.about_sdg' => 'About & SDG 5',
    'footer.safety_tips' => 'Safer browsing tips',
    'footer.privacy_note' => 'Your account and messages are encrypted. Admins cannot read them. Your companion can.',
    'footer.credit' => '© :tahun Haven. A safe place to report and recover.',

    // ---------- Shared buttons and words ----------
    'common.hours' => 'hours',
    'common.optional' => '(optional)',
    'common.back' => 'Back',
    'common.next' => 'Next',
    'common.choose' => 'Choose',
    'common.copy' => 'Copy',
    'common.download' => 'Download as text',
    'common.errors.title' => 'Some answers need a fix.',
    'common.errors.body' => 'We opened the first answer that needs a fix. The rest is still saved, but you need to pick your evidence files again.',

    // ---------- Report form: frame ----------
    'report.form.aria' => 'Report form',
    'report.steps.aria' => 'Report steps',
    'report.steps.count' => 'Step :sekarang of :total',
    'report.step1' => 'What happened',
    'report.step2' => 'Your story',
    'report.step3' => 'Time & place',
    'report.step4' => 'Contact & send',
    'report.submit' => 'Send report',

    // ---------- Report form: step 1 ----------
    'report.what.title' => 'What happened to you?',
    'report.what.lede' => 'The first three questions decide who accompanies you and how fast your report is handled. If no type of incident fits, choose Not sure. You can correct this later.',
    'report.category.legend' => 'Type of incident',
    'report.danger.legend' => 'Are you in danger right now?',
    'report.danger.yes' => 'Yes, I need help now',
    'report.danger.no' => 'Not right now',
    'report.danger.alert.title' => 'Get help first if you are in danger.',
    'report.danger.alert.body' => 'You can still send this report once you are safe, and it will sit at the top of the admin queue.',
    'report.reporter.legend' => 'You are reporting as',
    'report.reporter.victim' => 'I am the person it happened to',
    'report.reporter.witness' => 'I am a witness, or I am reporting for someone else',
    'report.age.legend' => 'Is the person under 18 years old?',
    'report.age.hint' => 'Child cases go straight to the highest priority and are handled by a companion trained for children. Choose "Not sure" if you do not know for certain.',
    'report.age.yes' => 'Yes, they are under 18',
    'report.age.no' => 'No, they are 18 or older',
    'report.age.unsure' => 'Not sure',
    'report.victim_contact.label' => 'Their contact',
    'report.victim_contact.hint' => 'A number, an email, or another way to reach them, up to :maks characters. This answer is stored encrypted. Haven does not contact them before they agree to be accompanied, so leave it empty if they do not know you are reporting.',

    // ---------- Report form: step 2 ----------
    'report.story.title' => 'What happened?',
    'report.story.lede' => 'Write it in your own words. You can skip the parts that are hard to tell. Admins cannot read this, only the companion assigned to you.',
    'report.story.label' => 'What happened',
    'report.story.hint' => 'For example: where it happened, who the person is to you (no name needed), and what you need now.',
    'report.ongoing.legend' => 'Is this still going on?',

    // ---------- Report form: step 3 ----------
    'report.where.title' => 'When and where?',
    'report.where.lede' => 'We use the area to pick a companion and nearby services. Every answer in this step is optional.',
    'report.date.label' => 'Approximate date',
    'report.place.label' => 'Type of place',
    'report.place.choose' => 'Choose a type of place',
    'report.province.label' => 'Province',
    'report.province.choose' => 'Choose a province',
    'report.city.label' => 'City or regency',
    'report.city.placeholder' => 'For example: Banyumas',
    'report.relation.label' => 'Your relationship to the person who did it',

    // ---------- Report form: step 4 ----------
    'report.contact.title' => 'How may a companion reach you?',
    'report.contact.lede' => 'Messages inside Haven are always on. Tick email or WhatsApp only if nobody else opens them.',
    'report.identity.legend' => 'Send this report as',
    'report.identity.anon' => 'Anonymous',
    'report.identity.anon.desc' => 'You get a report code and a PIN. Keep both to read and answer your companion.',
    'report.identity.account' => 'With your account :nama',
    'report.identity.account.desc' => 'The report shows up on your dashboard, so you do not need to remember a code and PIN.',
    'report.identity.signin' => 'Want the report in your account? <a href=":masuk">Sign in</a> or <a href=":daftar">create an account</a> first. You can also send it anonymously now and link it to an account later with the code and PIN.',
    'report.channel.legend' => 'Ways to reach you',
    'report.channel.inapp' => 'Secure messages in Haven (always on)',
    'report.channel.email' => 'Email',
    'report.channel.wa' => 'WhatsApp',
    'report.email.label' => 'Safe email',
    'report.email.hint' => 'Your companion only sends a notice here, never the message itself.',
    'report.wa.label' => 'WhatsApp number',
    'report.wa.hint' => 'Use a number only you hold. Your companion will not mention Haven in the first line of a message.',
    'report.files.label' => 'Supporting evidence',
    'report.files.drop' => 'Choose files or drop them here',
    'report.files.hint' => 'Screenshots, photos, or documents in JPG, PNG, or PDF. Up to :file files, 5 MB each. The original file names are encrypted.',
    'report.review.title' => 'Check before you send',
    'report.summary.reporter' => 'Reporting as',
    'report.summary.category' => 'Type',
    'report.summary.age' => 'Person is under 18',
    'report.summary.area' => 'Area',
    'report.summary.date' => 'Date',
    'report.summary.relation' => 'Relationship to the person who did it',
    'report.summary.story' => 'What happened',
    'report.consent.label' => 'I understand that only the assigned companion can read my story, and that I can ask for the report to be deleted through my companion.',

    // ---------- Report form: side panel ----------
    'report.aside.title' => 'Before you start',
    'report.aside.time' => 'Usually 5 to 10 minutes. The Back button does not erase your answers.',
    'report.aside.privacy' => 'Admins only see the type of incident and the area. Not your story or your contact.',
    'report.aside.exit' => 'If someone comes close, press Esc twice. The page switches to a weather search right away.',

    // ---------- After sending ----------
    'sent.title' => 'Your report is in.',
    'sent.lede' => 'It is now in the admin queue. Your companion replies on the report page, so keep the code and PIN below. Without both, an anonymous report cannot be opened again.',
    'sent.code' => 'Report code',
    'sent.pin' => 'PIN',
    'sent.copy_code' => 'Copy code',
    'sent.copy_pin' => 'Copy PIN',
    'sent.pin_once.title' => 'This PIN appears only once.',
    'sent.pin_once.body' => 'We only keep a scrambled version, so we cannot tell you this PIN again. Copy it somewhere nobody else opens, or download it as text.',
    'sent.open' => 'Open my report',
    'sent.sla' => 'A companion replies within :jam hours.',
    'sent.reachable.title' => 'You can call these now',
    'sent.reachable.lede' => 'Services open 24 hours whose numbers our team has called. The date we checked is next to each number.',
    'sent.next.title' => 'What happens next',
    'sent.reading.title' => 'Something to read now',

    // ---------- Tracking page ----------
    'track.title' => 'Open an anonymous report',
    'track.lede' => 'Enter the code and PIN from the page you saw after sending your report. Inside you find the status, messages from your companion, and call schedules.',
    'track.code.label' => 'Report code',
    'track.pin.label' => 'PIN',
    'track.submit' => 'Open report',
    'track.lock.note' => 'After 5 wrong tries, this page locks for 15 minutes. That keeps other people from guessing your PIN.',
    'track.forgot.title' => 'Lost your code or PIN?',
    'track.forgot.body' => 'We cannot recover them, because we only keep a scrambled version of the PIN. File a new report and write in your story that it continues an earlier one.',
    'track.forgot.cta' => 'File a new report',
    'track.account.title' => 'Have an account?',
    'track.account.body' => 'Reports sent with an account open from your dashboard without a code and PIN.',
    'track.account.cta' => 'Sign in',
    'track.status.title' => 'Report status',
    'track.messages.title' => 'Messages from your companion',
    'track.close' => 'Close this report session on this device',
    'track.sla' => 'Reply deadline: :jam hours from your last message.',
    'track.referral.title' => 'Referrals for a child case',
    'track.referral.unverified' => 'Needs verification',
];
