<?php
declare(strict_types=1);

namespace Haven\Tests\Database;

use Database;
use DateTimeImmutable;
use Haven\Tests\Support\TestDatabase;
use PHPUnit\Framework\TestCase;
use Report;
use Sla;

/**
 * Tes integrasi lencana "Lewat SLA" di antrean admin (Req 6.8).
 *
 * Keputusan lewat SLA ada di `Sla::isOverdue` dan diuji terpisah sebagai aturan
 * murni. Yang dijaga di sini adalah kolom yang harus benar-benar sampai ke
 * baris antrean: tanpa `last_survivor_at` dan `last_reply_at`, setiap kasus yang
 * sudah dibalas pendamping akan salah diberi lencana karena `Sla::isOverdue`
 * hanya melihat `created_at`.
 */
final class QueueSlaTest extends TestCase
{
    /** Waktu acuan tetap, supaya hasilnya tidak bergantung jam saat tes jalan. */
    private const NOW = '2026-02-10 12:00:00';

    /** SLA uji: 2 jam untuk kasus bahaya, 48 jam untuk sisanya. */
    private const ENV = [Sla::ENV_DANGER => '2', Sla::ENV_DEFAULT => '48'];

    private int $seq = 0;

    protected function setUp(): void
    {
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            $this->markTestSkipped($reason);
        }

        TestDatabase::bind();
        TestDatabase::truncate('report_status_logs', 'messages', 'reports', 'users');
    }

    protected function tearDown(): void
    {
        TestDatabase::release();
    }

    /** Baris antrean membawa waktu pesan penyintas dan balasan pendamping terakhir. */
    public function testQueueRowsCarryTheReplyTimestampsTheBadgeNeeds(): void
    {
        $code = $this->report(['created_at' => '2026-02-08 08:00:00']);
        $id = (int) Database::value('SELECT id FROM reports WHERE code = ?', [$code]);
        $this->message($id, 'survivor', '2026-02-09 09:00:00');
        $this->message($id, 'survivor', '2026-02-10 07:00:00');
        $this->message($id, 'companion', '2026-02-10 08:30:00');

        $row = $this->row($code);

        $this->assertSame('2026-02-10 07:00:00', $row['last_survivor_at'], 'Pesan penyintas terakhir, bukan yang pertama.');
        $this->assertSame('2026-02-10 08:30:00', $row['last_reply_at']);
    }

    /** Kasus tanpa pesan apa pun tetap membawa kedua kolom, berisi null. */
    public function testQueueRowsWithoutMessagesStillCarryBothColumns(): void
    {
        $row = $this->row($this->report([]));

        $this->assertArrayHasKey('last_survivor_at', $row);
        $this->assertArrayHasKey('last_reply_at', $row);
        $this->assertNull($row['last_survivor_at']);
        $this->assertNull($row['last_reply_at']);
    }

    /** Pesan `system` bukan balasan pendamping, jadi tidak boleh menghentikan hitungan SLA. */
    public function testSystemMessagesAreNotCountedAsACompanionReply(): void
    {
        $code = $this->report(['in_danger' => 1, 'created_at' => '2026-02-10 08:00:00']);
        $id = (int) Database::value('SELECT id FROM reports WHERE code = ?', [$code]);
        $this->message($id, 'system', '2026-02-10 11:50:00');

        $row = $this->row($code);

        $this->assertNull($row['last_reply_at']);
        $this->assertTrue($this->overdue($row), 'Laporan bahaya 4 jam tanpa balasan pendamping sudah lewat SLA 2 jam.');
    }

    /** Lencana muncul pada kasus yang menunggu lebih lama dari SLA-nya, dan tidak pada yang lain. */
    public function testBadgeAppearsOnlyOnCasesPastTheirSla(): void
    {
        $dangerLate = $this->report(['in_danger' => 1, 'created_at' => '2026-02-10 09:00:00']);
        $dangerFresh = $this->report(['in_danger' => 1, 'created_at' => '2026-02-10 11:00:00']);
        $plainLate = $this->report(['created_at' => '2026-02-08 06:00:00']);
        $plainFresh = $this->report(['created_at' => '2026-02-09 08:00:00']);

        $answered = $this->report(['in_danger' => 1, 'created_at' => '2026-02-08 08:00:00']);
        $answeredId = (int) Database::value('SELECT id FROM reports WHERE code = ?', [$answered]);
        $this->message($answeredId, 'survivor', '2026-02-10 09:00:00');
        $this->message($answeredId, 'companion', '2026-02-10 11:30:00');

        $overdue = [];
        foreach (Report::queue([], 20, 0) as $row) {
            $overdue[$row['code']] = $this->overdue($row);
        }

        $this->assertSame(
            [$dangerLate => true, $dangerFresh => false, $plainLate => true, $plainFresh => false, $answered => false],
            [
                $dangerLate => $overdue[$dangerLate],
                $dangerFresh => $overdue[$dangerFresh],
                $plainLate => $overdue[$plainLate],
                $plainFresh => $overdue[$plainFresh],
                $answered => $overdue[$answered],
            ]
        );
    }

    private function overdue(array $row): bool
    {
        return Sla::isOverdue($row, new DateTimeImmutable(self::NOW), self::ENV);
    }

    /** @return array<string,mixed> baris antrean untuk satu kode */
    private function row(string $code): array
    {
        foreach (Report::queue([], 20, 0) as $row) {
            if ($row['code'] === $code) {
                return $row;
            }
        }
        $this->fail("Laporan $code tidak ada di antrean.");
    }

    private function report(array $row): string
    {
        $code = 'HV-' . str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);
        $at = $row['created_at'] ?? '2026-02-10 09:00:00';
        Database::insert(
            'INSERT INTO reports (code, pin_hash, category, in_danger, province, city, status, chronology_enc, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $code,
                'hash-uji',
                $row['category'] ?? 'kdrt',
                (int) ($row['in_danger'] ?? 0),
                'Jawa Tengah',
                'Banyumas',
                $row['status'] ?? 'diterima',
                'ciphertext-uji',
                $at,
                $at,
            ]
        );

        return $code;
    }

    private function message(int $reportId, string $role, string $at): void
    {
        Database::insert(
            'INSERT INTO messages (report_id, sender_role, sender_id, body_enc, created_at) VALUES (?, ?, NULL, ?, ?)',
            [$reportId, $role, 'ciphertext-uji', $at]
        );
    }
}
