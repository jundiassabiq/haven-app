<?php
declare(strict_types=1);

/** Module 4 (companion): own cases only. Opening a case decrypts it, so every open is audited. */
final class CompanionCaseController
{
    /** Transitions a companion may make (closing without support is the admin's call). */
    private const COMPANION_NEXT = ['dirujuk', 'selesai'];

    public function index(): void
    {
        $f = ['q' => mb_substr(input('q'), 0, 20), 'status' => input('status'), 'urgensi' => input('urgensi')];
        View::show('pages/companion/cases', [
            'title' => 'Kasus saya | Haven',
            'active' => 'kasus',
            'rows' => Report::assignedTo((int) Auth::id(), $f),
            'f' => $f,
        ], 'app');
    }

    public function show(array $p): void
    {
        $r = CaseAccess::require($p['code'], 'companion');
        Audit::log('report.view', 'report', $r['code'], 'Kronologi didekripsi');
        [$upcoming] = Booking::split(Booking::forReport((int) $r['id']));
        View::show('pages/companion/case', [
            'title' => $r['code'] . ' | Haven',
            'active' => 'kasus',
            'report' => $r,
            'chronology' => Report::chronology($r),
            'contact' => Report::contact($r),
            'attachments' => Report::attachments((int) $r['id']),
            'notes' => CaseNote::forReport((int) $r['id']),
            'logs' => Report::statusLogs((int) $r['id']),
            'upcoming' => $upcoming,
            'services' => Database::all('SELECT id, name, is_verified FROM services ORDER BY is_verified DESC, name'),
            'service' => $r['service_id'] ? Service::find((int) $r['service_id']) : null,
            'consent' => ReportConsent::forReport((int) $r['id']),
            'unread' => Message::unreadFor((int) $r['id'], 'companion'),
            'next' => array_values(array_intersect(CaseWorkflow::allowedNext($r['status']), self::COMPANION_NEXT)),
        ], 'app');
    }

    public function updateStatus(array $p): void
    {
        $r = CaseAccess::require($p['code'], 'companion');
        $back = '/pendamping/kasus/' . $r['code'];
        $to = input('status');
        $serviceId = (int) input('layanan');
        $note = mb_substr(input('catatan'), 0, 500);
        $old = ['status' => $to, 'layanan' => (string) $serviceId, 'catatan' => $note];

        $errors = [];
        if (!in_array($to, self::COMPANION_NEXT, true)) {
            $errors['status'] = 'Pilih status berikutnya.';
        }
        if (!input_bool('sepakat')) {
            $errors['sepakat'] = 'Pastikan penyintas sudah setuju.';
        }
        if ($to === 'dirujuk' && (!$serviceId || !Service::find($serviceId))) {
            $errors['layanan'] = 'Pilih layanan tujuan rujukan.';
        }
        if ($errors) {
            back_with($back, $errors, $old);
        }
        try {
            CaseWorkflow::transition($r, $to, ['service_id' => $serviceId ?: null, 'note' => $note]);
        } catch (DomainException $e) {
            back_with($back, ['status' => $e->getMessage()], $old);
        }
        Session::flash('success', 'Status diperbarui menjadi ' . Report::STATUS[$to] . '. Penyintas menerima notifikasi.');
        redirect($back);
    }

    /**
     * Mencatat bahwa korban dari laporan saksi setuju didampingi (Req 5.7).
     * Hanya pendamping yang ditugaskan boleh mencatatnya (`CaseAccess`), token
     * CSRF diperiksa `App::run` sebelum aksi ini berjalan (Req 10.6), dan
     * pencatatannya masuk audit dalam request yang sama (Req 10.5).
     */
    public function recordConsent(array $p): void
    {
        $r = CaseAccess::require($p['code'], 'companion');
        $back = '/pendamping/kasus/' . $r['code'] . '#persetujuan';

        if ((string) ($r['reporter_type'] ?? 'korban') !== ConsentGuard::WITNESS) {
            back_with($back, ['konfirmasi' => 'Laporan ini dibuat korban sendiri, jadi tidak ada persetujuan korban yang perlu dicatat.']);
        }
        if (!input_bool('konfirmasi')) {
            back_with($back, ['konfirmasi' => 'Centang pernyataan bahwa korban sudah setuju didampingi.']);
        }
        if (!ReportConsent::record((int) $r['id'], (int) Auth::id())) {
            Session::flash('info', 'Persetujuan korban untuk kasus ini sudah tercatat sebelumnya.');
            redirect($back);
        }

        Audit::log('report.consent', 'report', $r['code'], 'Pendamping mencatat persetujuan korban');
        Session::flash('success', 'Persetujuan korban tercatat. Kasus ini sekarang bisa dirujuk ke layanan.');
        redirect($back);
    }

    public function addNote(array $p): void
    {
        $r = CaseAccess::require($p['code'], 'companion');
        $back = '/pendamping/kasus/' . $r['code'] . '#catatan';
        $in = ['title' => mb_substr(input('title'), 0, 120), 'body' => input('body')];
        $errors = Validator::make($in, ['body' => 'required|max:3000'], ['body.required' => 'Tulis catatan sebelum menyimpan.']);
        if ($errors) {
            back_with($back, $errors, $in);
        }
        CaseNote::add((int) $r['id'], (int) Auth::id(), $in['title'], $in['body']);
        Audit::log('note.create', 'report', $r['code'], 'Catatan internal ditambahkan');
        Session::flash('success', 'Catatan disimpan dan dienkripsi.');
        redirect($back);
    }
}
