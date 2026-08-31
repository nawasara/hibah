<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master penerima bantuan.
 *
 * Sebelumnya penerima hanya kolom teks di tiap usulan, dan akibatnya
 * "MI Contoh 01" yang menerima tiga tahun berturut-turut adalah tiga teks
 * terpisah yang kebetulan sama. Tidak ada yang dapat menjawab "berapa total
 * yang sudah diterima lembaga ini" tanpa mengelompokkan ulang tiap kali —
 * dan pengelompokan itu rapuh terhadap perbedaan ejaan.
 *
 * Dengan tabel ini, penerima jadi entitas: usulan menunjuk kepadanya, dan
 * riwayat penerimaan terbaca langsung dari relasinya.
 *
 * Dibuat SEKARANG karena tabel usulan masih kosong. Setelah data pengganti
 * masuk, memisahkan penerima berarti membelah baris hidup beserta seluruh
 * FK-nya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_hibah_recipients', function (Blueprint $table) {
            $table->id();

            $table->text('name');
            $table->text('address')->nullable();

            // ⚠️ Nilainya HARUS sama dengan ApprovedProposal::RECIPIENT_TYPES.
            // Migrasi tidak dapat membaca konstanta model, jadi daftarnya
            // ditulis dua kali — mengubah salah satu berarti mengubah keduanya.
            $table->enum('type', [
                'lembaga',
                'kelompok_masyarakat',
                'instansi_vertikal',
                'perorangan',
                'pemerintah_desa',
            ])->index('hibah_recipients_type_idx');

            // Bentuk yang dapat dibandingkan — dasar penggabungan penerima
            // yang ejaannya berbeda ("MI Muhammadiyah 14 Beton" vs
            // "MI MUHAMMADIYAH 14 BETON.").
            $table->string('name_normalized', 191)->nullable();
            $table->string('address_normalized', 191)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            // Satu baris per (nama, alamat) ternormalisasi.
            //
            // Nama saja TIDAK cukup: "MDT MIFTAHUL HUDA" dipakai banyak
            // madrasah di alamat berbeda, dan menyatukannya akan
            // menggabungkan lembaga yang sungguh berbeda menjadi satu.
            $table->unique(
                ['name_normalized', 'address_normalized'],
                'hibah_recipients_identity_unq',
            );
        });

        Schema::table('nawasara_hibah_approved_proposals', function (Blueprint $table) {
            // Nullable: baris impor yang penerimanya belum terpetakan tetap
            // tersimpan. Menolaknya berarti membuang usulan yang sah hanya
            // karena master penerimanya belum lengkap.
            $table->foreignId('recipient_id')->nullable()->after('opd_id')
                ->constrained('nawasara_hibah_recipients')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('nawasara_hibah_approved_proposals', function (Blueprint $table) {
            $table->dropForeign(['recipient_id']);
            $table->dropColumn('recipient_id');
        });

        Schema::dropIfExists('nawasara_hibah_recipients');
    }
};
