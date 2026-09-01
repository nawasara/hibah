<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Database\Seeders;

use Illuminate\Database\Seeder;
use Nawasara\Hibah\Models\ApprovedProposal;
use Nawasara\Hibah\Models\Disbursement;
use Nawasara\Registry\Models\Opd;

/**
 * 100 usulan contoh, diambil dari data produksi v0.1.
 *
 * Bukan data karangan: nama penerima, nomenklatur program, dan nomor SK
 * semuanya nyata, jadi halaman yang dibangun di atasnya menghadapi teks
 * sepanjang yang sungguh terjadi — nomenklatur kegiatan pemerintah rutin
 * melewati 255 karakter, dan itu yang dulu menggagalkan impor.
 *
 * Sampelnya dipilih MERATA per kombinasi peruntukan × bentuk, bukan 100
 * baris pertama. Seratus baris pertama seluruhnya hibah uang, dan seeder
 * seperti itu tidak pernah melatih aturan penerima yang justru paling mudah
 * salah — terutama bansos uang yang hanya boleh ke perorangan.
 *
 *   hibah/uang 45 · hibah/barang 11 · bansos/uang 22 · bk/uang 22
 *
 * ⚠️ HANYA untuk lingkungan pengembangan. Menjalankannya di produksi akan
 * mencampur data contoh dengan data sungguhan, dan keduanya sulit
 * dibedakan setelah tercampur.
 */
class SampleProposalSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('SampleProposalSeeder dilewati: lingkungan produksi.');
            $this->command?->line('Untuk paparan, pakai: php artisan hibah:sample --install');

            return;
        }

        $this->seed();
    }

    /**
     * Jalankan tanpa penjagaan lingkungan.
     *
     * HANYA dipanggil SampleDataCommand, yang menandai tiap baris supaya
     * dapat dicabut kembali tepat sasaran — itulah yang membuat pemasangan
     * di produksi dapat dipertanggungjawabkan, bukan karena penjagaannya
     * dianggap berlebihan.
     */
    public function runForPresentation(): void
    {
        $this->seed();
    }

    private function seed(): void
    {
        $rows = require dirname(__DIR__, 3).'/database/seeders/sample-proposals.php';

        $opdIds = Opd::query()->pluck('id')->all();

        if ($opdIds === []) {
            $this->command?->warn('Tidak ada OPD di registry — seeder dilewati.');

            return;
        }

        $created = 0;

        foreach ($rows as $i => $row) {
            // OPD dibagi berputar: tiap OPD kebagian data, sehingga
            // penyaringan per-OPD dan ScopedToOpd benar-benar teruji.
            $row['opd_id'] = $opdIds[$i % count($opdIds)];

            $proposal = ApprovedProposal::withoutGlobalScopes()->create($row);

            $this->seedDisbursements($proposal, $i);

            $created++;
        }

        $this->command?->info("SampleProposalSeeder: {$created} usulan dibuat.");
    }

    /**
     * Realisasi bertahap supaya KETIGA status muncul di daftar.
     *
     * Tanpa ini semuanya "Disahkan", dan lencana status tidak pernah terlihat
     * bekerja — termasuk yang menandai anggaran disetujui kosong.
     */
    private function seedDisbursements(ApprovedProposal $proposal, int $index): void
    {
        $budget = (int) $proposal->approved_budget;

        if ($budget <= 0) {
            return;   // biar sebagian tetap "Disahkan"
        }

        // Sepertiga cair penuh, sepertiga sebagian, sepertiga belum.
        $mode = $index % 3;

        if ($mode === 2) {
            return;
        }

        $total = $mode === 0 ? $budget : (int) round($budget * 0.4);

        foreach ([1 => 0.5, 2 => 0.5] as $quarter => $share) {
            Disbursement::create([
                'approved_proposal_id' => $proposal->getKey(),
                'quarter' => $quarter,
                'disbursed_amount' => (int) round($total * $share),
            ]);
        }

        $proposal->unsetRelation('disbursements');

        if ($proposal->recalculateStatus()) {
            $proposal->save();
        }
    }
}
