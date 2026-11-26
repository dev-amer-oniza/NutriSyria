<?php

namespace Tests\Feature;

use App\Auth\Access;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The account menu of the dashboard leads back to the public site, for every role. */
class PanelSiteLinkTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function roles(): array
    {
        return ['admin' => [Access::SUPER_ADMIN], 'brand owner' => [Access::BRAND_OWNER], 'nutritionist' => [Access::NUTRITIONIST]];
    }

    #[DataProvider('roles')]
    public function test_every_role_finds_the_site_in_the_account_menu(string $role): void
    {
        $this->signInAs($role);

        $this->get(Filament::getPanel('admin')->getUrl())
            ->assertOk()
            ->assertSee('الذهاب للموقع')
            ->assertSee('href="'.route('home').'"', false);
    }
}
