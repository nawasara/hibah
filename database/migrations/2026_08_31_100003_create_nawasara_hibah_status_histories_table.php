<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak perubahan status.
 *
 * Tetap dicatat meski status kini DIHITUNG dari realisasi, bukan dipilih
 * staf. Justru karena otomatis: tanpa jejak ini, riwayat sebuah usulan
 * melompat dari "Disahkan" ke "Cair" tanpa menyebut siapa dan kapan — dan
 * itulah yang dicari ketika angkanya dipertanyakan.
 *
 * `by_user_id` diisi staf yang menyimpan realisasinya, bukan sistem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_hibah_status_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('approved_proposal_id')
                ->constrained('nawasara_hibah_approved_proposals')
                ->cascadeOnDelete();

            // Nullable: baris pertama sebuah usulan tidak punya status asal.
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);

            $table->foreignId('by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // Wajib diisi saat pembatalan (§8) — usulan batal tanpa
            // keterangan akan ditanyakan saat audit, dan yang mengetahui
            // alasannya sudah lupa.
            $table->text('notes')->nullable();

            // Hanya created_at: baris riwayat tidak pernah disunting.
            $table->timestamp('created_at')->nullable();

            $table->index(['approved_proposal_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_hibah_status_histories');
    }
};
