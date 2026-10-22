<?php

use App\Auth\Access;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * A nutritionist's public profile on the site (the directory and its pages), and the
 * permission to have one and take clients, given to the nutritionist role by default.
 * Times are DATETIME (XAMPP).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutritionist_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('display_name', 80)->nullable();
            $table->string('photo')->nullable();
            $table->json('specialties')->nullable();
            $table->string('city', 32)->nullable()->index();
            $table->unsignedTinyInteger('years_experience')->nullable();
            $table->text('bio')->nullable();
            $table->string('consultation_mode', 16)->nullable();
            $table->string('clinic_address', 200)->nullable();
            $table->boolean('accepting_clients')->default(false)->index();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate(Access::CONSULTATIONS_PROVIDE, 'web');
        Role::findOrCreate(Access::NUTRITIONIST, 'web')->givePermissionTo(Access::CONSULTATIONS_PROVIDE);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('nutritionist_profiles');
        Permission::query()->where('name', Access::CONSULTATIONS_PROVIDE)->get()->each->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
