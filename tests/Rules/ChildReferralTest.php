<?php
declare(strict_types=1);

namespace Haven\Tests\Rules;

use ChildReferral;
use PHPUnit\Framework\TestCase;
use ServiceRules;

/**
 * Tes unit panel rujukan anak (Req 4.4, 4.5, 4.6): tiga entri selalu ada, dan
 * nomor layanan yang belum dicek tim tidak pernah ikut tampil.
 */
final class ChildReferralTest extends TestCase
{
    /** Satu baris tabel `services` secukupnya untuk ServiceRules::publicView. */
    private static function row(array $over = []): array
    {
        return $over + [
            'id' => 1,
            'name' => 'Layanan contoh',
            'type' => 'hotline',
            'province' => 'Nasional',
            'city' => 'Nasional',
            'hours' => '24 jam',
            'tags' => 'Anak',
            'phone' => null,
            'whatsapp' => null,
            'is_verified' => 0,
            'verified_at' => null,
        ];
    }

    /** Req 4.5: masukan kosong pun menghasilkan tiga entri bernama, dalam urutan slot. */
    public function testPanelAlwaysHasThreeNamedEntriesEvenWithoutAnyService(): void
    {
        $panel = ChildReferral::panel([]);

        $this->assertCount(3, $panel);
        $this->assertSame(['uptd_ppa_anak', 'kpai', 'sapa_129'], array_column($panel, 'key'));
        foreach ($panel as $entry) {
            $this->assertNotSame('', $entry['name'], 'Entri panel tanpa layanan tetap harus bernama.');
            $this->assertNull($entry['contact']);
            $this->assertFalse($entry['verified']);
            $this->assertSame(ServiceRules::UNVERIFIED_LABEL, $entry['label']);
        }
    }

    /** Req 4.5: layanan terverifikasi menampilkan nama dan nomornya, plus tanggal cek DD-MM-YYYY. */
    public function testVerifiedServiceShowsItsContact(): void
    {
        $panel = ChildReferral::panel([
            'sapa_129' => self::row(['name' => 'SAPA 129', 'phone' => '129', 'is_verified' => 1, 'verified_at' => '2026-05-17']),
        ]);
        $entry = $panel[2];

        $this->assertSame('SAPA 129', $entry['name']);
        $this->assertTrue($entry['verified']);
        $this->assertSame('129', $entry['contact']);
        $this->assertSame('phone', $entry['contact_kind']);
        $this->assertSame('17-05-2026', $entry['verified_at']);
        $this->assertNull($entry['label']);
    }

    /** Req 4.5: tanpa telepon, WhatsApp layanan terverifikasi yang dipakai sebagai kontak. */
    public function testWhatsappIsUsedWhenThereIsNoPhone(): void
    {
        $panel = ChildReferral::panel([
            'kpai' => self::row(['name' => 'KPAI', 'whatsapp' => '081100002222', 'is_verified' => 1, 'verified_at' => '2026-05-17']),
        ]);
        $entry = $panel[1];

        $this->assertSame('081100002222', $entry['contact']);
        $this->assertSame('whatsapp', $entry['contact_kind']);
    }

    /**
     * Req 4.6: layanan yang belum terverifikasi tampil dengan namanya sendiri,
     * berlabel "Perlu verifikasi" dan tanpa nomor, sementara entri lain tetap utuh.
     */
    public function testUnverifiedServiceKeepsItsNameButHidesTheContact(): void
    {
        $panel = ChildReferral::panel([
            'uptd_ppa_anak' => self::row(['name' => 'UPTD PPA Jawa Tengah', 'phone' => '024000111', 'is_verified' => 0]),
            'sapa_129' => self::row(['name' => 'SAPA 129', 'phone' => '129', 'is_verified' => 1, 'verified_at' => '2026-05-17']),
        ]);

        $this->assertSame('UPTD PPA Jawa Tengah', $panel[0]['name']);
        $this->assertFalse($panel[0]['verified']);
        $this->assertNull($panel[0]['contact']);
        $this->assertNull($panel[0]['contact_kind']);
        $this->assertSame('Perlu verifikasi', $panel[0]['label']);
        $this->assertNotContains('024000111', $panel[0], 'Nomor layanan yang belum dicek tidak boleh ikut di entri panel.');

        // Dua entri lain tidak terpengaruh.
        $this->assertSame(ServiceRules::UNVERIFIED_LABEL, $panel[1]['label']);
        $this->assertSame('129', $panel[2]['contact']);
    }

    /**
     * Baris yang ditandai terverifikasi tetapi kontaknya kosong tidak punya apa
     * pun untuk ditawarkan, jadi entrinya tetap berlabel "Perlu verifikasi"
     * (Req 1.11, 4.6).
     */
    public function testVerifiedRowWithoutAnyContactStaysLabelledUnverified(): void
    {
        $panel = ChildReferral::panel([
            'kpai' => self::row(['name' => 'KPAI', 'is_verified' => 1, 'verified_at' => '2026-05-17']),
        ]);

        $this->assertFalse($panel[1]['verified']);
        $this->assertNull($panel[1]['contact']);
        $this->assertNull($panel[1]['verified_at']);
        $this->assertSame(ServiceRules::UNVERIFIED_LABEL, $panel[1]['label']);
    }

    /** Nilai null atau bertipe lain dari seed yang belum lengkap tidak boleh membuat galat. */
    public function testNullAndUnexpectedValuesFallBackToAnEntryWithoutContact(): void
    {
        $panel = ChildReferral::panel(['uptd_ppa_anak' => null, 'kpai' => 'bukan baris', 'sapa_129' => 0]);

        $this->assertCount(3, $panel);
        foreach ($panel as $entry) {
            $this->assertNull($entry['contact']);
            $this->assertSame(ServiceRules::UNVERIFIED_LABEL, $entry['label']);
            $this->assertNotSame('', $entry['name']);
        }
    }
}
