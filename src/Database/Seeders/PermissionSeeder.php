<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permission nawasara/hibah.
 *
 * Digerbang per PERUNTUKAN, bukan per submenu: staf yang boleh melihat
 * hibah uang praktis selalu boleh melihat hibah barang, jadi permission
 * per-submenu menggandakan jumlahnya tanpa memisahkan apa pun yang nyata.
 *
 * Ketiganya saat ini diberikan ke role yang sama — staf hibah dan bansos
 * orang yang sama. Tetap dipisah karena membelahnya kelak berarti menyentuh
 * role yang sudah hidup di produksi, sedangkan memisahkannya sekarang tidak
 * berbiaya apa pun.
 *
 * ⚠️ Seeder ini hanya MENAMBAH. Nama permission lama (`hibah.pengajuan.*`,
 * `hibah.laporan.*`, `hibah.kategori.manage`) dibuang oleh migrasi
 * rename-permission — bukan di sini, karena nama-nama itu tersimpan di
 * basis data dan sudah dipegang role.
 */
class PermissionSeeder extends Seeder
{
    /** Peruntukan yang punya menunya sendiri. */
    private const PURPOSES = ['hibah', 'bansos', 'bantuan-keuangan'];

    public function run(): void
    {
        $permissions = [];

        foreach (self::PURPOSES as $purpose) {
            $permissions[] = "hibah.{$purpose}.view";
            $permissions[] = "hibah.{$purpose}.create";
            $permissions[] = "hibah.{$purpose}.update";
        }

        $permissions = array_merge($permissions, [
            // Berlaku lintas peruntukan — pencatatan realisasi dan
            // pembatalan tidak dipisah per menu.
            'hibah.approved-proposal.view',
            'hibah.approved-proposal.update',
            'hibah.disbursement.update',
            'hibah.report.export',

            // Admin — impor massal lintas OPD.
            'hibah.import',
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Seluruh set diberikan ke `developer` untuk kemudahan pengembangan.
        // Di produksi, role operator mendapat sebagiannya lewat UI
        // manajemen user, bukan dari sini.
        $role = Role::where('name', 'developer')->first();

        if ($role) {
            $role->givePermissionTo($permissions);
        }
    }
}
