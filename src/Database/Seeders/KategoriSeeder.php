<?php

namespace Nawasara\Hibah\Database\Seeders;

use Illuminate\Database\Seeder;
use Nawasara\Hibah\Models\Kategori;

class KategoriSeeder extends Seeder
{
    public function run(): void
    {
        // Seed values observed in the 2024-2026 Excel data. Admin can add
        // more via the Master Kategori UI (hibah.kategori.manage).
        $kategori = [
            'HIBAH UANG',
            'HIBAH BARANG',
            'BANSOS',
            'BANTUAN KEUANGAN',
        ];

        foreach ($kategori as $nama) {
            Kategori::firstOrCreate(['nama' => $nama], ['aktif' => true]);
        }
    }
}
