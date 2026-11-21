<?php
declare(strict_types=1);

namespace Haven\Tests\Rules;

use Config;
use PHPUnit\Framework\TestCase;
use Sla;

/**
 * Helper tampilan SLA yang dipakai halaman terkirim, halaman lacak, dan antrean
 * admin (Req 6.2, 6.8): `sla_hours()` membaca jam dari konfigurasi, dan
 * `sla_overdue()` menilai satu baris antrean pada waktu sekarang.
 */
final class SlaDisplayTest extends TestCase
{
    private ?string $danger = null;
    private ?string $default = null;

    protected function setUp(): void
    {
        $this->danger = Config::get(Sla::ENV_DANGER);
        $this->default = Config::get(Sla::ENV_DEFAULT);
        Config::set(Sla::ENV_DANGER, '2');
        Config::set(Sla::ENV_DEFAULT, '48');
    }

    protected function tearDown(): void
    {
        Config::set(Sla::ENV_DANGER, (string) $this->danger);
        Config::set(Sla::ENV_DEFAULT, (string) $this->default);
    }

    /** Req 6.2: halaman menampilkan jam dari konfigurasi, bukan angka yang ditulis di view. */
    public function testHoursShownComeFromTheConfiguration(): void
    {
        $this->assertSame(2, sla_hours(true));
        $this->assertSame(48, sla_hours(false));

        Config::set(Sla::ENV_DANGER, '3');
        $this->assertSame(3, sla_hours(true));

        Config::set(Sla::ENV_DANGER, 'dua');
        $this->assertSame(2, sla_hours(true), 'Nilai tidak sah kembali ke default.');
    }

    /** Req 6.8: baris yang menunggu lebih lama dari SLA-nya ditandai, yang lain tidak. */
    public function testOverdueFollowsTheWaitingTimeOfTheRow(): void
    {
        $this->assertTrue(sla_overdue([
            'in_danger' => 1,
            'created_at' => $this->ago('4 hours'),
            'last_survivor_at' => null,
            'last_reply_at' => null,
        ]));

        $this->assertFalse(sla_overdue([
            'in_danger' => 1,
            'created_at' => $this->ago('30 minutes'),
            'last_survivor_at' => null,
            'last_reply_at' => null,
        ]));

        $this->assertFalse(sla_overdue([
            'in_danger' => 0,
            'created_at' => $this->ago('4 hours'),
            'last_survivor_at' => null,
            'last_reply_at' => null,
        ]), 'Kasus biasa punya batas 48 jam.');
    }

    /** Pesan penyintas yang sudah dibalas pendamping tidak lagi dihitung menunggu. */
    public function testAnsweredCasesAreNotMarkedOverdue(): void
    {
        $case = [
            'in_danger' => 1,
            'created_at' => $this->ago('3 days'),
            'last_survivor_at' => $this->ago('5 hours'),
            'last_reply_at' => $this->ago('10 minutes'),
        ];
        $this->assertFalse(sla_overdue($case));

        $case['last_reply_at'] = $this->ago('6 hours');
        $this->assertTrue(sla_overdue($case), 'Balasan lebih lama dari pesan penyintas berarti masih menunggu.');
    }

    private function ago(string $interval): string
    {
        return date('Y-m-d H:i:s', strtotime("-$interval"));
    }
}
