<?php

use App\Auth\Access;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * "مراجع الوصفات والأغذية" (the owner's decisions 1a 2a 3a): a new built-in role that approves
 * products' official values and reviews imported foods, so products waiting for approval reach
 * it, not the nutritionists. The nutritionist keeps consultations only. Roles get an Arabic
 * name of their own (roles made from the panel), and permissions an editable name and
 * description (the permissions page).
 */
return new class extends Migration
{
    private const MOVED = [Access::IMPORTS_VIEW, Access::IMPORTS_REVIEW, Access::PRODUCTS_VIEW_ALL, Access::NUTRITION_APPROVE];

    public function up(): void
    {
        $tables = config('permission.table_names');
        Schema::table($tables['roles'], function (Blueprint $table) {
            $table->string('label')->nullable()->after('name');
        });
        Schema::table($tables['permissions'], function (Blueprint $table) {
            $table->string('label')->nullable()->after('name');
            $table->string('description', 300)->nullable()->after('label');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (Access::permissions() as $name => $label) {
            Permission::findOrCreate($name, 'web');
            DB::table($tables['permissions'])->where('name', $name)->update(['label' => $label, 'description' => Access::descriptions()[$name] ?? null]);
        }

        Role::findOrCreate(Access::REVIEWER, 'web')->givePermissionTo(self::MOVED);
        foreach (Access::builtInRoles() as $name => $label) {
            DB::table($tables['roles'])->where('name', $name)->update(['label' => $label]);
        }
        Role::findByName(Access::NUTRITIONIST, 'web')->revokePermissionTo(self::MOVED);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findByName(Access::NUTRITIONIST, 'web')->givePermissionTo(self::MOVED);
        Role::query()->where('name', Access::REVIEWER)->get()->each->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $tables = config('permission.table_names');
        Schema::table($tables['permissions'], function (Blueprint $table) {
            $table->dropColumn(['label', 'description']);
        });
        Schema::table($tables['roles'], function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }
};
