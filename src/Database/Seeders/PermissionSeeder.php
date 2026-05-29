<?php

namespace Nawasara\Hibah\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Operator OPD — entry & manage their own OPD's submissions
            'hibah.pengajuan.view',
            'hibah.pengajuan.create',
            'hibah.pengajuan.update',
            'hibah.realisasi.update',
            'hibah.laporan.view',
            'hibah.laporan.export',

            // Admin-Hibah — cross-OPD master + bulk import.
            // (User↔OPD membership moved to nawasara/registry; manage it
            // there via `registry.membership.manage`.)
            'hibah.kategori.manage',
            'hibah.import',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Grant the full set to `developer` for dev convenience. In
        // production, operator roles get the operator subset and admin-hibah
        // gets everything — configured via the user-management UI, not here.
        $role = Role::where('name', 'developer')->first();
        if ($role) {
            $role->givePermissionTo($permissions);
        }
    }
}
