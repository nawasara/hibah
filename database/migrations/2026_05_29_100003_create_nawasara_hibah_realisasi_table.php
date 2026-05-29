<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_hibah_realisasi', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pengajuan_id')
                ->constrained('nawasara_hibah_pengajuan')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('triwulan'); // 1-4
            $table->unsignedBigInteger('realisasi_anggaran')->default(0);

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Satu baris realisasi per (pengajuan, triwulan).
            $table->unique(['pengajuan_id', 'triwulan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_hibah_realisasi');
    }
};
