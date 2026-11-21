<?php
declare(strict_types=1);

namespace Haven\Tests\Rules;

use Assignment;
use CaseWorkflow;
use PHPUnit\Framework\TestCase;

/**
 * Tes unit pagar penugasan `CaseWorkflow::assignmentError` (Req 4.9, 4.10):
 * pendamping di luar daftar kandidat ditolak, dan jalur peringatan kasus anak
 * hanya lewat setelah Admin mengonfirmasi.
 */
final class AssignmentGateTest extends TestCase
{
    /** @return array<string,mixed> baris pendamping secukupnya untuk Assignment. */
    private static function companion(int $id, string $region, bool $trained): array
    {
        return ['id' => $id, 'name' => 'Pendamping ' . $id, 'region' => $region, 'child_trained' => (int) $trained, 'active' => 0];
    }

    /** @return array<string,mixed> baris laporan secukupnya untuk Assignment. */
    private static function report(bool $isChild, string $city = 'Banyumas'): array
    {
        return ['id' => 1, 'is_child' => (int) $isChild, 'city' => $city, 'province' => 'Jawa Tengah'];
    }

    /** Req 4.9: pendamping terlatih di wilayah laporan lewat tanpa konfirmasi apa pun. */
    public function testTrainedCompanionInTheReportRegionIsAccepted(): void
    {
        $candidates = Assignment::candidates([
            self::companion(1, 'Banyumas', true),
            self::companion(2, 'Banyumas', false),
        ], self::report(true));

        $this->assertNull(CaseWorkflow::assignmentError($candidates, 1, false));
    }

    /** Req 4.9: pendamping yang tidak ditawarkan tetap ditolak walau Admin mengirim id-nya. */
    public function testCompanionOutsideTheOfferedListIsRejected(): void
    {
        $candidates = Assignment::candidates([
            self::companion(1, 'Banyumas', true),
            self::companion(2, 'Banyumas', false),
        ], self::report(true));

        $error = CaseWorkflow::assignmentError($candidates, 2, false);

        $this->assertNotNull($error);
        $this->assertStringContainsString('tidak ada dalam daftar', $error);
    }

    /** Penugasan tanpa pendamping sama sekali juga ditolak, bukan dianggap kandidat kosong. */
    public function testMissingCompanionIdIsRejected(): void
    {
        $candidates = Assignment::candidates([self::companion(1, 'Banyumas', true)], self::report(true));

        $this->assertNotNull(CaseWorkflow::assignmentError($candidates, 0, true));
    }

    /** Req 4.10: tanpa pendamping terlatih di wilayah laporan, penugasan butuh confirm. */
    public function testWarningPathNeedsAnExplicitConfirmation(): void
    {
        $candidates = Assignment::candidates([
            self::companion(1, 'Purbalingga', true),
            self::companion(2, 'Banyumas', false),
        ], self::report(true));

        $this->assertTrue($candidates['needsConfirm']);
        $this->assertSame(Assignment::WARNING, CaseWorkflow::assignmentError($candidates, 2, false));
        $this->assertNull(CaseWorkflow::assignmentError($candidates, 2, true), 'Konfirmasi Admin harus menyelesaikan penugasan.');
    }

    /** Kasus bukan anak tidak diatur Req 4.9 dan 4.10: setiap pendamping aktif boleh menerima. */
    public function testNonChildCaseAcceptsAnyActiveCompanionWithoutConfirmation(): void
    {
        $candidates = Assignment::candidates([
            self::companion(1, 'Semarang', false),
            self::companion(2, 'Banyumas', false),
        ], self::report(false));

        $this->assertFalse($candidates['needsConfirm']);
        $this->assertNull(CaseWorkflow::assignmentError($candidates, 1, false));
        $this->assertNull(CaseWorkflow::assignmentError($candidates, 2, false));
    }
}
