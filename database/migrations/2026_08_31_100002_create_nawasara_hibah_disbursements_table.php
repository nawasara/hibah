<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Realisasi pencairan per triwulan.
 *
 * Tabel inilah yang MENENTUKAN status usulan: jumlah seluruh barisnya
 * dibandingkan `approved_budget` menghasilkan approved / partially_disbursed
 * / disbursed. Karena itu ia bukan sekadar catatan pendamping — ia sumber
 * kebenaran untuk keadaan sebuah usulan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_hibah_disbursements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('approved_proposal_id')
                ->constrained('nawasara_hibah_approved_proposals')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('quarter');
            $table->unsignedBigInteger('disbursed_amount')->default(0);

            $table->foreignId('updated_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Satu baris per triwulan per usulan. Tanpa ini, penyimpanan
            // berulang menumpuk baris ganda dan jumlahnya — yang menentukan
            // status — jadi berlipat.
            $table->unique(['approved_proposal_id', 'quarter'], 'hibah_disbursements_proposal_quarter_unq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_hibah_disbursements');
    }
};
