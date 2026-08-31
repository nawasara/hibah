<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usulan bantuan daerah yang SUDAH DISAHKAN.
 *
 * Bukan "pengajuan": keputusannya sudah diambil di luar sistem, dan yang
 * dicatat di sini hasilnya. Karena itu tidak ada alur setujui/tolak — yang
 * masih berubah setelah pengesahan adalah pencairannya.
 *
 * Satu tabel memuat hibah, bansos, dan bantuan keuangan sekaligus. Menunya
 * dipisah tiga (lihat config/menu.php), tetapi itu keputusan tampilan;
 * ketiganya berbagi seluruh kolom dan hanya dibedakan `purpose`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_hibah_approved_proposals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('opd_id')
                ->constrained('nawasara_registry_opd')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('fiscal_year')->index('hibah_proposals_year_idx');

            // ── Tiga sumbu yang menggantikan kolom kategori teks bebas ──
            //
            // ⚠️ Nilai di bawah HARUS sama dengan konstanta di
            // ApprovedProposal (PURPOSES, FORMS, RECIPIENT_TYPES, BK_TYPES).
            // Migrasi tidak dapat membaca konstanta model — ia berjalan di
            // keadaan yang tidak menjamin model termuat — jadi daftarnya
            // ditulis dua kali. Mengubah salah satu berarti mengubah keduanya.
            $table->enum('purpose', ['hibah', 'bansos', 'bk'])->index('hibah_proposals_purpose_idx');
            $table->enum('form', ['uang', 'barang']);
            $table->enum('recipient_type', [
                'lembaga',
                'kelompok_masyarakat',
                'instansi_vertikal',
                'perorangan',
                'pemerintah_desa',
            ]);

            // Sub-jenis Bantuan Keuangan. NULL untuk hibah & bansos.
            //
            // ADD = Alokasi Dana Desa, DD = Dana Desa. Keduanya bantuan
            // keuangan khusus ke pemerintah desa, dan keduanya ditawarkan
            // di formulir.
            $table->enum('bk_type', ['umum', 'add', 'dd'])->nullable();

            // ── Asal usulan ──
            $table->text('proposer')->nullable();
            $table->string('dapil')->nullable();
            $table->boolean('cross_dapil')->default(false);
            $table->text('proposal_dictionary')->nullable();
            $table->date('proposed_at')->nullable();

            // ── Nomenklatur anggaran ──
            //
            // text(), bukan string(): sumbernya formulir Excel OPD, dan
            // nomenklatur program/kegiatan pemerintah rutin melewati 255
            // karakter. Impor 4.441 baris pernah gagal di tengah jalan
            // karena kolom sejenis dibatasi VARCHAR.
            $table->text('program')->nullable();
            $table->text('activity')->nullable();
            $table->text('sub_activity')->nullable();

            // ── Penerima ──
            $table->text('recipient_name');
            $table->text('recipient_address')->nullable();

            // Kolom normalisasi terpisah untuk deteksi duplikat. string(191)
            // supaya dapat di-index di utf8mb4; yang mentah tetap text.
            //
            // ⚠️ Nama index ditulis EKSPLISIT. Nama otomatis Laravel
            // (`{tabel}_{kolom}_index`) melewati batas 64 karakter MySQL di
            // sini — tabelnya 33 karakter, kolomnya 28 — dan migrasinya
            // gagal dengan "Identifier name is too long".
            $table->string('recipient_name_normalized', 191)->nullable();
            $table->string('recipient_address_normalized', 191)->nullable();
            $table->index('recipient_name_normalized', 'hibah_proposals_name_norm_idx');
            $table->index('recipient_address_normalized', 'hibah_proposals_addr_norm_idx');

            // ── Anggaran ──
            $table->unsignedBigInteger('budget_before')->default(0);
            $table->unsignedBigInteger('budget_after')->nullable();

            $table->text('decree')->nullable();
            $table->unsignedBigInteger('approved_budget')->nullable();

            // Diisi tangan dan dapat basi — TIDAK dipakai menghitung status.
            // Yang menentukan status adalah jumlah baris disbursements.
            $table->unsignedBigInteger('undisbursed_budget')->nullable();
            $table->text('undisbursed_reason')->nullable();

            $table->string('monev_proof_path')->nullable();
            $table->text('notes')->nullable();

            // Status pencairan — DIHITUNG dari realisasi, bukan dipilih staf.
            // Satu-satunya yang dinyatakan manusia adalah 'cancelled'.
            $table->enum('status', [
                'approved',
                'partially_disbursed',
                'disbursed',
                'cancelled',
            ])->default('approved')->index('hibah_proposals_status_idx');

            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['opd_id', 'fiscal_year'], 'hibah_proposals_opd_year_idx');

            // Menu menyaring dengan pasangan ini; tanpa index, tiap halaman
            // memindai seluruh tabel.
            $table->index(['purpose', 'form'], 'hibah_proposals_purpose_form_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_hibah_approved_proposals');
    }
};
