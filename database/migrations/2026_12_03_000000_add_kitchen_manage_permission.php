<?php

use App\Auth\Access;
use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * «إدارة المطبخ السوري»: the Syrian kitchen section, until now the admin's alone, for any role
 * given this permission from the roles page (the owner's request). No role has it at first.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate(Access::KITCHEN_MANAGE, 'web');
        DB::table(config('permission.table_names.permissions'))->where('name', Access::KITCHEN_MANAGE)->update([
            'label' => Access::permissions()[Access::KITCHEN_MANAGE],
            'description' => Access::descriptions()[Access::KITCHEN_MANAGE],
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', Access::KITCHEN_MANAGE)->get()->each->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
