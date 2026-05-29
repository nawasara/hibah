<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_hibah_pengajuan', function (Blueprint $table) {
            $table->id();

            // Scope & klasifikasi
            $table->foreignId('opd_id')
                ->constrained('nawasara_registry_opd')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun')->index();
            $table->foreignId('kategori_id')
                ->nullable()
                ->constrained('nawasara_hibah_kategori')
                ->nullOnDelete();
            $table->enum('peruntukan', ['hibah', 'bansos', 'bk'])->default('hibah');

            // Identitas usulan — free-text from the form, widen to text.
            $table->text('pengusul')->nullable();
            $table->string('dapil')->nullable();
            $table->boolean('lintas_dapil')->default(false);
            $table->text('kamus_usulan')->nullable();
            $table->date('tanggal_proposal')->nullable();

            // Program / kegiatan — gov nomenclature is long; TEXT avoids
            // truncation on real imports.
            $table->text('program')->nullable();
            $table->text('kegiatan')->nullable();
            $table->text('sub_kegiatan')->nullable();

            // Penerima — sumber deteksi duplikat. Raw columns are TEXT because
            // some source rows put a long belanja description in the recipient
            // field. Duplicate detection groups on the *_normalized columns,
            // which are capped at 191 chars (utf8mb4 index-safe) and indexed.
            $table->text('nama_penerima');
            $table->text('alamat_penerima')->nullable();
            $table->string('nama_penerima_normalized', 191)->nullable()->index();
            $table->string('alamat_penerima_normalized', 191)->nullable()->index();

            // Anggaran usulan
            $table->unsignedBigInteger('anggaran_sebelum')->default(0);
            $table->unsignedBigInteger('anggaran_setelah')->nullable();

            // Verifikasi awal (MS = memenuhi syarat / TMS = tidak)
            $table->enum('status_verifikasi', ['ms', 'tms'])->nullable();
            $table->string('bukti_verifikasi_path')->nullable();

            // Hasil rapat — diisi saat status → disetujui. SK text in the
            // real data is a full paragraph ("Keputusan Bupati Nomor ...
            // Tentang Penetapan Penerima Hibah ..."), so TEXT not VARCHAR.
            $table->text('sk_kepala_daerah')->nullable();
            $table->unsignedBigInteger('anggaran_disetujui')->nullable();

            // Realisasi & monev (ringkasan; rincian per-triwulan di tabel anak)
            $table->unsignedBigInteger('anggaran_belum_cair')->nullable();
            $table->text('alasan_belum_cair')->nullable();
            $table->string('bukti_monev_path')->nullable();
            $table->text('keterangan')->nullable();

            // Lifecycle
            $table->enum('status', ['diajukan', 'disetujui', 'ditolak', 'selesai'])
                ->default('diajukan')
                ->index();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Composite index untuk laporan per OPD per tahun (query terpanas).
            $table->index(['opd_id', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_hibah_pengajuan');
    }
};
