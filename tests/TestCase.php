<?php

namespace Tests;

use App\Auth\Access;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Users, roles and permissions live on the fixed "central" connection (MySQL in
     * production). In tests it shares the in-memory SQLite database, so migrations that write
     * roles and tests that create users see the same tables.
     */
    protected function setUpTraits()
    {
        config(['database.connections.central' => config('database.connections.sqlite')]);
        $this->shareCentralConnection();      // for the migrations of the first test

        $uses = parent::setUpTraits();

        $this->shareCentralConnection();      // later tests restore a cached in-memory database

        return $uses;
    }

    private function shareCentralConnection(): void
    {
        DB::purge('central');
        DB::connection('central')->setPdo(DB::connection()->getPdo());
    }

    /** Signs in to the admin panel as a new active user with the given role (roles come from the migrations). */
    protected function signInAs(string $role = Access::SUPER_ADMIN, array $attributes = []): User
    {
        $user = User::forceCreate($attributes + [
            'name' => $role, 'email' => $role.'-'.uniqid().'@example.test', 'password' => 'secret-password', 'is_active' => true,
        ]);
        $user->assignRole($role);

        $this->actingAs($user);
        Filament::setCurrentPanel('admin');

        return $user;
    }
}
