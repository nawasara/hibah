<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Tests;

use Nawasara\Hibah\Models\ApprovedProposal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Isolasi peruntukan (audit keamanan 15 Oktober 2026, temuan S2).
 *
 * `purpose`, `purposeSegment`, `segment`, dan `form` dulu properti publik
 * biasa. Staf bansos dapat menukar `purpose` menjadi `hibah` dari peramban
 * lalu melihat, mengekspor, atau menghapus usulan hibah, karena middleware
 * rute tidak berjalan pada aksi Livewire.
 */
class PurposeIsolationTest extends TestCase
{
    public function test_permission_disusun_dari_purpose_basis_data(): void
    {
        $this->assertSame('hibah.hibah.view', ApprovedProposal::permissionFor('hibah', 'view'));
        $this->assertSame('hibah.bansos.update', ApprovedProposal::permissionFor('bansos', 'update'));

        // Satu-satunya pemetaan yang tidak sepele: `bk` di kolom,
        // `bantuan-keuangan` di nama permission.
        $this->assertSame('hibah.bantuan-keuangan.create', ApprovedProposal::permissionFor('bk', 'create'));
    }

    /** Peruntukan atau aksi karangan harus null, supaya pemanggil menolak. */
    public function test_nilai_karangan_menghasilkan_null(): void
    {
        $this->assertNull(ApprovedProposal::permissionFor('bantuan-keuangan', 'view'));
        $this->assertNull(ApprovedProposal::permissionFor('', 'view'));
        $this->assertNull(ApprovedProposal::permissionFor(null, 'view'));
        $this->assertNull(ApprovedProposal::permissionFor('hibah', 'delete'));
        $this->assertNull(ApprovedProposal::permissionFor('hibah', 'view.x'));
    }

    /** @return array<string, array{0: string, 1: list<string>}> */
    public static function lockedProperties(): array
    {
        return [
            'Section\Table' => ['Proposal/Section/Table.php', ['purpose', 'form', 'bkType', 'purposeSegment', 'segment']],
            'Form' => ['Proposal/Form.php', ['purposeSegment', 'segment', 'purpose', 'form', 'proposal']],
            'Index' => ['Proposal/Index.php', ['purposeSegment', 'segment', 'purpose', 'form', 'bkType']],
            'Detail' => ['Proposal/Detail.php', ['proposal', 'purpose', 'segment']],
            'Report' => ['Report/Index.php', ['purpose', 'detailIds', 'detailName']],
        ];
    }

    /** @param  list<string>  $properties */
    #[DataProvider('lockedProperties')]
    public function test_properti_konteks_terkunci(string $file, array $properties): void
    {
        $src = (string) file_get_contents(dirname(__DIR__).'/src/Livewire/'.$file);

        foreach ($properties as $property) {
            $this->assertMatchesRegularExpression(
                '/#\[Locked\]\s+public [^$;]*\$'.$property.'\b/',
                $src,
                "{$file}: \${$property} tidak #[Locked], peramban dapat menggantinya",
            );
        }
    }

    /** `#[Url]` pada purpose laporan membuat `?purpose=hibah` mengisinya. */
    public function test_purpose_laporan_tanpa_url(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__).'/src/Livewire/Report/Index.php');

        // Buang komentar supaya penjelasan tentang #[Url] tidak dianggap kode.
        $code = preg_replace('#/\*.*?\*/#s', '', $src) ?? $src;
        $code = preg_replace('#//[^\n]*#', '', $code) ?? $code;

        $this->assertDoesNotMatchRegularExpression('/#\[Url\][^;]*public string \$purpose\b/s', $code);
    }

    /** Aksi hapus dan simpan memeriksa izin dari purpose, bukan dari segmen. */
    public function test_aksi_tidak_memakai_segmen_untuk_izin(): void
    {
        foreach (['Proposal/Section/Table.php', 'Proposal/Form.php'] as $file) {
            $src = (string) file_get_contents(dirname(__DIR__).'/src/Livewire/'.$file);

            $this->assertStringNotContainsString("'hibah.'.\$this->purposeSegment", $src, $file);
            $this->assertStringContainsString('authorizePurpose(', $src, $file);
        }
    }
}
