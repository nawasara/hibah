<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mengganti nama permission v0.1.x ke skema v0.2.0.
 *
 * ⚠️ **Ini WAJIB migrasi, bukan cukup memperbarui PermissionSeeder.**
 *
 * Nama permission tersimpan di basis data dan sudah dipegang role. Seeder
 * yang diperbarui hanya MENAMBAH nama baru; yang lama tertinggal sebagai
 * baris yatim, role tetap memegangnya, dan pengguna kehilangan akses tanpa
 * pesan apa pun — menunya hanya lenyap dari sidebar, yang terbaca seperti
 * paketnya rusak.
 *
 * Mengganti NAMA baris yang ada (bukan menghapus lalu membuat) membuat
 * seluruh pemberian role ikut terbawa. Tabel pivotnya menunjuk id, dan id
 * itu tidak berubah.
 *
 * Pemetaannya tidak satu-ke-satu: `hibah.pengajuan.view` yang dulu tunggal
 * kini terpecah tiga per peruntukan. Yang lama dipetakan ke padanan
 * `hibah` — peruntukan terbesar — lalu dua sisanya DITAMBAHKAN ke role yang
 * sama, karena staf hibah dan bansos memang orang yang sama. Menghilangkan
 * langkah kedua akan mencabut akses bansos dari orang yang selama ini
 * memilikinya.
 */
return new class extends Migration
{
    /** lama => baru */
    private const RENAMES = [
        'hibah.pengajuan.view' => 'hibah.hibah.view',
        'hibah.pengajuan.create' => 'hibah.hibah.create',
        'hibah.pengajuan.update' => 'hibah.hibah.update',
        'hibah.laporan.export' => 'hibah.report.export',
        'hibah.realisasi.update' => 'hibah.disbursement.update',
    ];

    /** Dicabut — halamannya sudah tidak ada. */
    private const DROPPED = [
        'hibah.kategori.manage',
        'hibah.laporan.view',

        // Tidak dirujuk kode mana pun — sisa dari sebelum manajemen
        // operator OPD pindah ke nawasara/registry
        // (`registry.membership.manage`).
        'hibah.operator.manage',
    ];

    public function up(): void
    {
        $table = config('permission.table_names.permissions', 'permissions');
        $pivot = config('permission.table_names.role_has_permissions', 'role_has_permissions');

        foreach (self::RENAMES as $old => $new) {
            $existing = DB::table($table)->where('name', $old)->first();

            if ($existing === null) {
                continue;
            }

            // Kalau nama barunya sudah ada (seeder sempat berjalan lebih
            // dulu), yang lama dibuang saja — menggantinya akan melanggar
            // unique index dan menggagalkan seluruh migrasi.
            if (DB::table($table)->where('name', $new)->exists()) {
                DB::table($pivot)->where('permission_id', $existing->id)->delete();
                DB::table($table)->where('id', $existing->id)->delete();

                continue;
            }

            DB::table($table)->where('id', $existing->id)->update(['name' => $new]);
        }

        foreach (self::DROPPED as $name) {
            $row = DB::table($table)->where('name', $name)->first();

            if ($row === null) {
                continue;
            }

            DB::table($pivot)->where('permission_id', $row->id)->delete();
            DB::table($table)->where('id', $row->id)->delete();
        }

        $this->mirrorToOtherPurposes($table, $pivot);
    }

    /**
     * Role yang kini memegang `hibah.hibah.*` juga diberi padanan bansos
     * dan bantuan-keuangan.
     *
     * Tanpa ini, staf yang sebelumnya melihat seluruh data hanya akan
     * melihat menu Hibah — dua menu lainnya hilang dari sidebar tanpa
     * penjelasan.
     */
    private function mirrorToOtherPurposes(string $table, string $pivot): void
    {
        foreach (['view', 'create', 'update'] as $action) {
            $source = DB::table($table)->where('name', "hibah.hibah.{$action}")->first();

            if ($source === null) {
                continue;
            }

            $roleIds = DB::table($pivot)
                ->where('permission_id', $source->id)
                ->pluck('role_id');

            if ($roleIds->isEmpty()) {
                continue;
            }

            foreach (['bansos', 'bantuan-keuangan'] as $purpose) {
                $name = "hibah.{$purpose}.{$action}";

                $id = DB::table($table)->where('name', $name)->value('id')
                    ?? DB::table($table)->insertGetId([
                        'name' => $name,
                        'guard_name' => 'web',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                foreach ($roleIds as $roleId) {
                    DB::table($pivot)->updateOrInsert(
                        ['permission_id' => $id, 'role_id' => $roleId],
                        [],
                    );
                }
            }
        }
    }

    public function down(): void
    {
        $table = config('permission.table_names.permissions', 'permissions');

        foreach (self::RENAMES as $old => $new) {
            DB::table($table)->where('name', $new)->update(['name' => $old]);
        }

        // Permission yang dicabut tidak dibuat ulang: halamannya sudah tidak
        // ada, jadi mengembalikannya hanya menyisakan baris yang tak
        // menggerbangi apa pun.
    }
};
