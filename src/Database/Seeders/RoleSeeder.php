<?php

namespace Nawasara\Hibah\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Creates the two hibah roles. Run AFTER PermissionSeeder (it needs the
 * hibah.* permissions to exist).
 *
 *   hibah-operator — OPD staff: entry + manage their own OPD's submissions
 *                    and reports. MUST also be linked to an OPD via the
 *                    registry "Keanggotaan OPD" page (registry membership
 *                    row) — that link activates the OPD scope. Without it,
 *                    an operator is "restricted" and sees NOTHING (fail-closed).
 *
 *   hibah-admin    — Admin-Hibah: every hibah permission, including master
 *                    bulk import. Terdaftar sebagai privileged role di
 *                    ApprovedProposal, jadi (tanpa membership) melihat semua OPD.
 *
 * Idempotent — safe to re-run.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Ketiga peruntukan diberikan bersama: staf hibah dan bansos orang
        // yang sama. Permissionnya tetap dipisah supaya kelak dapat
        // dibelah tanpa menyentuh skema — lihat PermissionSeeder.
        $operatorPerms = [];

        foreach (['hibah', 'bansos', 'bantuan-keuangan'] as $purpose) {
            $operatorPerms[] = "hibah.{$purpose}.view";
            $operatorPerms[] = "hibah.{$purpose}.create";
            $operatorPerms[] = "hibah.{$purpose}.update";
        }

        $operatorPerms = array_merge($operatorPerms, [
            'hibah.approved-proposal.view',
            'hibah.approved-proposal.update',
            'hibah.disbursement.update',
            'hibah.recipient.view',
            'hibah.report.export',
        ]);

        $adminPerms = array_merge($operatorPerms, [
            'hibah.import',
        ]);

        // Guard: ensure permissions exist (PermissionSeeder should run first).
        foreach ($adminPerms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $operator = Role::firstOrCreate(['name' => 'hibah-operator', 'guard_name' => 'web']);
        $operator->syncPermissions($operatorPerms);

        $admin = Role::firstOrCreate(['name' => 'hibah-admin', 'guard_name' => 'web']);
        $admin->syncPermissions($adminPerms);
    }
}
