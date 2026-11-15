<?php

namespace Tests;

use App\Auth\Access;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
