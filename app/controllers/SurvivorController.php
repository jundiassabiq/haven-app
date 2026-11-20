<?php
declare(strict_types=1);

/** Survivor account pages: reports list and schedule. */
final class SurvivorController
{
    public function reports(): void
    {
        View::show('pages/survivor/reports', [
            'title' => 'Laporan saya | Haven',
            'active' => 'laporan',
            'reports' => Report::forUser((int) Auth::id()),
        ], 'app');
    }

    public function schedule(): void
    {
        [$upcoming, $past] = Booking::split(Booking::forUser((int) Auth::id()));
        View::show('pages/survivor/schedule', ['title' => 'Jadwal sesi | Haven', 'active' => 'jadwal', 'upcoming' => $upcoming, 'past' => $past], 'app');
    }
}
