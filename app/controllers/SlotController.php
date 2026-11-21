<?php
declare(strict_types=1);

/** Module 5 (companion): open, delete, and cancel sessions. */
final class SlotController
{
    public function index(): void
    {
        View::show('pages/companion/slots', [
            'title' => 'Jadwal & slot | Haven',
            'active' => 'slot',
            'slots' => Slot::upcomingFor((int) Auth::id()),
        ], 'app');
    }

    public function store(): void
    {
        $in = ['tanggal' => input('tanggal'), 'jam' => input('jam'), 'durasi' => input('durasi'), 'cara' => input('cara'), 'lokasi' => mb_substr(input('lokasi'), 0, 160)];
        $err = Slot::validate($in['tanggal'], $in['jam'], (int) $in['durasi'], $in['cara'], $in['lokasi'], time());
        $startsAt = $in['tanggal'] . ' ' . $in['jam'] . ':00';
        if ($err === null && Slot::overlaps((int) Auth::id(), $startsAt, (int) $in['durasi'])) {
            $err = 'Waktu ini bertabrakan dengan slot lain milikmu.';
        }
        if ($err !== null) {
            back_with('/pendamping/slot', ['slot' => $err], $in);
        }
        $id = Slot::create((int) Auth::id(), $startsAt, (int) $in['durasi'], $in['cara'], $in['lokasi']);
        $label = Slot::label(Slot::find($id));
        Audit::log('slot.create', 'slot', (string) $id, $label);
        Session::flash('success', "Slot $label dibuka dan bisa dipesan penyintas.");
        redirect('/pendamping/slot');
    }

    public function delete(array $p): void
    {
        $slot = Slot::find((int) $p['id']);
        if (!$slot || (int) $slot['companion_id'] !== Auth::id()) {
            throw new HttpException(404);
        }
        if (!Slot::deleteFree((int) $slot['id'], (int) Auth::id())) {
            Session::flash('error', 'Slot ini sudah dipesan. Batalkan sesinya dulu jika memang perlu.');
            redirect('/pendamping/slot');
        }
        Audit::log('slot.delete', 'slot', (string) $slot['id'], Slot::label($slot));
        Session::flash('success', 'Slot ' . Slot::label($slot) . ' dihapus.');
        redirect('/pendamping/slot');
    }

    public function cancelBooking(array $p): void
    {
        $b = Booking::find((int) $p['id']);
        if (!$b || (int) $b['companion_id'] !== Auth::id()) {
            throw new HttpException(404);
        }
        try {
            Booking::cancel((int) $b['id'], 'companion');
        } catch (DomainException $e) {
            Session::flash('error', $e->getMessage());
            redirect('/pendamping/slot');
        }
        $report = Report::find((int) $b['report_id']);
        Notifier::survivor($report, 'jadwal', 'Sesi ' . Slot::label($b) . ' dibatalkan pendamping', '#jadwal');
        Audit::log('booking.cancel', 'report', $report['code'], Slot::label($b) . ' oleh pendamping');
        Session::flash('success', 'Sesi dibatalkan. Penyintas menerima notifikasi. Kirim pesan untuk menawarkan waktu lain.');
        redirect('/pendamping/slot');
    }
}
