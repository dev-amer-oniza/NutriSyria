<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** The old mobile API is gone (the owner's decisions 1a 2a 3a 4a): no route, no tokens, no OTP. */
class OldApiRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_old_api_routes_no_longer_exist(): void
    {
        $user = User::factory()->create(['password' => 'olive-oil-9']);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'olive-oil-9'])->assertNotFound();
        $this->postJson('/api/register', ['email' => 'new@example.com'])->assertNotFound();
        $this->postJson('/api/request-otp', ['email' => $user->email])->assertNotFound();
        $this->getJson('/api/home')->assertNotFound();
        $this->postJson('/broadcasting/auth')->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }

    public function test_the_old_token_and_otp_tables_are_dropped(): void
    {
        $this->assertFalse(Schema::hasTable('personal_access_tokens'));
        $this->assertFalse(Schema::hasTable('otp_codes'));
    }

    public function test_the_admin_panel_upload_route_is_livewire_s_own(): void
    {
        $route = app('router')->getRoutes()->getByName('livewire.upload-file');

        $this->assertNotNull($route);
        $this->assertContains('web', $route->gatherMiddleware());
        $this->assertEmpty(array_filter($route->gatherMiddleware(), fn ($m) => str_contains((string) $m, 'Tenancy')));
    }
}
