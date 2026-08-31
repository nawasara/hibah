<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Membuang tabel v0.1.x sebelum skema v0.2.0 dibuat.
 *
 * ⚠️ INI MENGHAPUS DATA. Di produksi berarti 9.009 baris pengajuan
 * 2024–2026. Itu keputusan yang diambil sadar: struktur kategorinya tidak
 * dapat diselamatkan, dan end user mengirim ulang data yang sudah sesuai
 * format baru.
 *
 * **Ekspor cadangan ke CSV sebelum menjalankan ini.** Berkas cadangan itu
 * yang membuat keputusan ini dapat dibatalkan; tanpanya tidak.
 *
 * Berkas migrasi v0.1.x-nya sendiri sudah dihapus dari paket, jadi migrasi
 * ini yang membereskan server yang terlanjur menjalankannya. Penamaannya
 * 090000 — lebih awal dari 100001 — supaya berjalan sebelum tabel baru
 * dibuat, karena nama tabel `status_histori` dan `status_histories` berbeda
 * tetapi FK-nya menunjuk tabel yang sama-sama bernama awal `nawasara_hibah_`.
 *
 * Tidak ada `down()` yang berarti: data yang dihapus tidak dapat
 * dikembalikan migrasi. Pemulihan berarti memuat ulang berkas cadangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Urutan penting: anak lebih dulu, karena FK-nya cascade ke induk.
        Schema::dropIfExists('nawasara_hibah_status_histori');
        Schema::dropIfExists('nawasara_hibah_realisasi');
        Schema::dropIfExists('nawasara_hibah_pengajuan');

        // Kategori teks bebas dipensiunkan — diganti tiga kolom terstruktur
        // di tabel approved_proposals. Dibuang, bukan disisakan kosong:
        // tabel yang tertinggal akan diisi lagi oleh importer berikutnya.
        Schema::dropIfExists('nawasara_hibah_kategori');
    }

    public function down(): void
    {
        // Sengaja kosong. Membuat ulang tabelnya tanpa datanya hanya
        // menghasilkan kerangka kosong yang menyesatkan — seolah datanya
        // pulih. Pemulihan sungguhan berarti memuat berkas cadangan CSV.
    }
};
