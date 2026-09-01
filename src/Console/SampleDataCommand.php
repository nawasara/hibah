<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Nawasara\Hibah\Database\Seeders\SampleProposalSeeder;
use Nawasara\Hibah\Models\ApprovedProposal;
use Nawasara\Hibah\Models\Recipient;

/**
 * Memasang dan mencabut data contoh — termasuk di produksi.
 *
 * SampleProposalSeeder menolak berjalan di produksi dengan sengaja: data
 * contoh yang tercampur data sungguhan hampir mustahil dipisahkan lagi.
 * Tetapi paparan kepada pimpinan membutuhkan halaman yang berisi, dan
 * menolak sepenuhnya hanya memindahkan pekerjaan ke pengisian manual yang
 * jauh lebih berantakan.
 *
 * Jalan keluarnya: data contoh DITANDAI, sehingga dapat dicabut kembali
 * tepat sasaran. Penandanya kolom `notes` yang diawali penanda di bawah —
 * bukan rentang id atau tanggal, yang keduanya akan menyapu data sungguhan
 * bila kelak ada yang masuk di sela-selanya.
 *
 *   php artisan hibah:sample --install
 *   php artisan hibah:sample --purge
 */
class SampleDataCommand extends Command
{
    /**
     * Penanda yang membuat pencabutan dapat dipercaya.
     *
     * Ditulis di awal `notes` setiap baris contoh. Baris yang tidak
     * membawanya TIDAK PERNAH disentuh oleh --purge, apa pun keadaannya.
     */
    public const MARKER = '[CONTOH]';

    protected $signature = 'hibah:sample
                            {--install : Pasang 100 usulan contoh}
                            {--purge : Cabut kembali seluruh data contoh}';

    protected $description = 'Pasang atau cabut data contoh hibah (aman untuk paparan di produksi)';

    public function handle(): int
    {
        if ($this->option('purge')) {
            return $this->purge();
        }

        if ($this->option('install')) {
            return $this->install();
        }

        $this->error('Pilih --install atau --purge.');

        return self::FAILURE;
    }

    private function install(): int
    {
        $existing = $this->markedQuery()->count();

        if ($existing > 0) {
            $this->warn("Sudah ada {$existing} usulan contoh. Jalankan --purge dulu bila ingin memasang ulang.");

            return self::FAILURE;
        }

        $real = ApprovedProposal::withoutGlobalScopes()->count();

        // Menolak bila sudah ada data sungguhan. Setelah tercampur, satu
        // penanda di kolom catatan adalah satu-satunya pembeda — dan itu
        // terlalu tipis untuk dipertaruhkan pada data hibah yang sungguhan.
        if ($real > 0) {
            $this->error("Sudah ada {$real} usulan di basis data.");
            $this->line('Data contoh hanya boleh dipasang saat tabel masih kosong,');
            $this->line('supaya pencabutannya kelak tidak dapat salah sasaran.');

            return self::FAILURE;
        }

        $this->info('Memasang data contoh...');

        // Seeder menolak lingkungan produksi; penandaan dilakukan setelahnya.
        $seeder = new SampleProposalSeeder;
        $seeder->setCommand($this);
        $seeder->runForPresentation();

        $ditandai = $this->markAll();

        $this->newLine();
        $this->info("Selesai. {$ditandai} usulan contoh terpasang dan DITANDAI.");
        $this->line('Cabut kembali dengan: php artisan hibah:sample --purge');

        return self::SUCCESS;
    }

    private function purge(): int
    {
        $ids = $this->markedQuery()->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('Tidak ada data contoh yang perlu dicabut.');

            return self::SUCCESS;
        }

        $this->warn("Akan mencabut {$ids->count()} usulan contoh beserta realisasinya.");

        if (! $this->option('no-interaction') && ! $this->confirm('Lanjutkan?', true)) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($ids) {
            DB::table('nawasara_hibah_disbursements')->whereIn('approved_proposal_id', $ids)->delete();
            DB::table('nawasara_hibah_status_histories')->whereIn('approved_proposal_id', $ids)->delete();
            ApprovedProposal::withoutGlobalScopes()->whereIn('id', $ids)->delete();

            // Penerima yang tidak lagi punya usulan apa pun ikut dicabut —
            // tetapi HANYA yang benar-benar kosong, supaya penerima yang
            // kelak dipakai data sungguhan tidak ikut terhapus.
            Recipient::query()->doesntHave('proposals')->delete();
        });

        $this->info('Data contoh dicabut.');

        return self::SUCCESS;
    }

    /** @return \Illuminate\Database\Eloquent\Builder<ApprovedProposal> */
    private function markedQuery()
    {
        return ApprovedProposal::withoutGlobalScopes()
            ->where('notes', 'like', self::MARKER.'%');
    }

    /**
     * Beri penanda pada seluruh baris yang baru dipasang.
     *
     * Dilakukan sekali di akhir, bukan per baris di seeder, supaya seeder
     * tetap dapat dipakai apa adanya di lingkungan pengembangan tanpa
     * catatan tambahan yang mengotori tampilan.
     */
    private function markAll(): int
    {
        return ApprovedProposal::withoutGlobalScopes()
            ->where(function ($q) {
                $q->whereNull('notes')->orWhere('notes', 'not like', self::MARKER.'%');
            })
            ->update([
                'notes' => DB::raw(
                    "CONCAT('".self::MARKER." ', COALESCE(notes, 'data contoh untuk paparan'))"
                ),
            ]);
    }
}
