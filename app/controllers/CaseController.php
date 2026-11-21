<?php
declare(strict_types=1);

/** Survivor side of a case (Modules 3, 5, 9) plus evidence download for the survivor and the assigned companion. */
final class CaseController
{
    public function show(array $p): void
    {
        $report = CaseAccess::require($p['code'], 'survivor');
        $accountView = CaseAccess::isOwner($report);
        $companion = $report['assigned_to'] ? User::find((int) $report['assigned_to']) : null;
        [$upcoming, $past] = Booking::split(Booking::forReport((int) $report['id']));
        $active = in_array($report['status'], CaseWorkflow::ACTIVE, true);

        $notifs = Notifier::forSurvivor($report);
        Notifier::markSurvivorRead($report);

        $data = [
            'title' => 'Laporan ' . $report['code'] . ' | Haven',
            'active' => $accountView ? 'laporan' : 'lacak',
            // Halaman kasus memakai tombol keluar cepat berlabel (Req 8.17).
            'quickExit' => 'labeled',
            'report' => $report,
            'logs' => Report::statusLogs((int) $report['id']),
            'companion' => $companion,
            'attachCount' => Report::attachmentCount((int) $report['id']),
            'notifs' => $notifs,
            'upcoming' => $upcoming,
            'past' => $past,
            'slots' => $active && $companion ? Slot::availableFor((int) $companion['id']) : [],
            'service' => $report['service_id'] ? Service::find((int) $report['service_id']) : null,
            // Req 4.4, 4.5: halaman laporan kasus anak selalu memuat panel rujukan anak.
            'childPanel' => !empty($report['is_child']) ? ChildReferral::panel(Service::findChildServices()) : null,
            // Req 6.2: batas waktu balasan yang berlaku untuk kasus ini, dalam jam.
            'slaHours' => sla_hours((bool) (int) $report['in_danger']),
            'unread' => Message::unreadFor((int) $report['id'], 'survivor'),
            'accountView' => $accountView,
        ];
        if ($accountView) {
            View::show('pages/case/survivor', $data, 'app');
        } else {
            View::show('pages/case/public-wrap', $data);
        }
    }

    public function book(array $p): void
    {
        $report = CaseAccess::require($p['code'], 'survivor');
        $slotId = (int) input('slot_id');
        try {
            $id = Booking::book($slotId, $report);
        } catch (DomainException $e) {
            Session::flash('error', $e->getMessage());
            redirect('/kasus/' . $report['code'] . '#jadwal');
        }
        $b = Booking::find($id);
        Notifier::user((int) $b['companion_id'], (int) $report['id'], 'jadwal', 'Sesi ' . Slot::label($b) . ' dipesan (' . $report['code'] . ')', '/pendamping/slot');
        Notifier::survivor($report, 'jadwal', 'Sesi ' . Slot::label($b) . ' dikonfirmasi', '#jadwal');
        Audit::log('booking.create', 'report', $report['code'], Slot::label($b), Auth::user(), Auth::user()['name'] ?? 'Anonim');
        Session::flash('success', 'Sesi ' . Slot::label($b) . ' dipesan.');
        redirect('/kasus/' . $report['code'] . '#jadwal');
    }

    public function cancel(array $p): void
    {
        $report = CaseAccess::require($p['code'], 'survivor');
        $b = Booking::find((int) $p['id']);
        if (!$b || (int) $b['report_id'] !== (int) $report['id']) {
            throw new HttpException(404);
        }
        try {
            Booking::cancel((int) $b['id'], 'survivor');
        } catch (DomainException $e) {
            Session::flash('error', $e->getMessage());
            redirect('/kasus/' . $report['code'] . '#jadwal');
        }
        Notifier::user((int) $b['companion_id'], (int) $report['id'], 'jadwal', 'Sesi ' . Slot::label($b) . ' dibatalkan penyintas (' . $report['code'] . ')', '/pendamping/slot');
        Audit::log('booking.cancel', 'report', $report['code'], Slot::label($b) . ' oleh penyintas', Auth::user(), Auth::user()['name'] ?? 'Anonim');
        Session::flash('success', 'Sesi ' . Slot::label($b) . ' dibatalkan.');
        redirect('/kasus/' . $report['code'] . '#jadwal');
    }

    /** Evidence is served only through this permission check, never as a static file. */
    public function attachment(array $p): void
    {
        $a = Database::one('SELECT a.*, r.code, r.user_id, r.assigned_to, r.id AS rid FROM report_attachments a JOIN reports r ON r.id = a.report_id WHERE a.id = ?', [(int) $p['id']]);
        if (!$a) {
            throw new HttpException(404);
        }
        $report = Report::find((int) $a['rid']);
        if (CaseAccess::side($report) === null) {
            Audit::log('access.denied', 'report', $report['code'], 'Membuka bukti tanpa izin');
            throw new HttpException(403);
        }
        $path = Upload::path($a['stored_name']);
        if (!is_file($path)) {
            throw new HttpException(404, 'File bukti tidak ditemukan di penyimpanan.');
        }
        $index = (int) Database::value('SELECT COUNT(*) FROM report_attachments WHERE report_id = ? AND id <= ?', [$a['rid'], $a['id']]);
        Audit::log('attachment.view', 'report', $report['code'], 'Bukti ke-' . $index, Auth::user(), Auth::user()['name'] ?? 'Anonim');

        header('Content-Type: ' . $a['mime']);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="bukti-' . $index . '.' . (Upload::TYPES[$a['mime']] ?? 'bin') . '"');
        header("Content-Security-Policy: sandbox; default-src 'none'; img-src 'self'; style-src 'unsafe-inline'");
        readfile($path);
    }
}
