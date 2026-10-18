<?php

use App\Auth\Access;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * The three panel roles and their starting permissions (App\Auth\Access), and the removal of
 * the old restaurant system's roles. Written as a migration so every installation gets the
 * same roles without running a seeder. Permissions a role already has are kept: the
 * super-admin may have changed them from the panel.
 */
return new class extends Migration
{
    private const OLD_ROLES = ['restaurant-owner', 'customer', 'delivery'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(Access::permissions()) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (Access::defaultPermissions() as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            if ($role->wasRecentlyCreated || $role->permissions()->doesntExist()) {
                $role->givePermissionTo($permissions);
            }
        }

        // Deleting a role also removes it from its users (model_has_roles cascades).
        Role::query()->whereIn('name', self::OLD_ROLES)->get()->each->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()->whereIn('name', [Access::BRAND_OWNER, Access::NUTRITIONIST])->get()->each->delete();
        Permission::query()->whereIn('name', array_keys(Access::permissions()))->get()->each->delete();
        foreach (self::OLD_ROLES as $name) {
            Role::findOrCreate($name, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
