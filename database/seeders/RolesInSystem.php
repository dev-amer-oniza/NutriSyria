<?php

namespace Database\Seeders;

use App\Auth\Access;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * The panel roles and permissions (App\Auth\Access). The migration
 * 2026_10_22_000002_set_up_panel_roles_and_permissions creates them already; this seeder only
 * makes a fresh `db:seed` safe to run on its own.
 */
class RolesInSystem extends Seeder
{
    public function run(): void
    {
        foreach (array_keys(Access::permissions()) as $name) {
            Permission::findOrCreate($name, 'web');
        }
        foreach (Access::defaultPermissions() as $role => $permissions) {
            $role = Role::findOrCreate($role, 'web');
            if ($role->permissions()->doesntExist()) {
                $role->givePermissionTo($permissions);
            }
        }
    }
}
