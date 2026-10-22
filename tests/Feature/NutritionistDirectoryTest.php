<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Filament\Clusters\Consultations\Pages\NutritionistProfilePage;
use App\Models\NutritionistProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NutritionistDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function nutritionist(string $email, array $profile = []): User
    {
        $user = User::forceCreate(['name' => $email, 'email' => $email, 'password' => 'x-secret-1', 'is_active' => true, 'email_verified_at' => now()]);
        $user->assignRole(Access::NUTRITIONIST);

        if ($profile !== []) {
            $user->nutritionistProfile()->create($profile + [
                'display_name' => 'د. '.$email, 'specialties' => ['diabetes'], 'city' => 'damascus',
                'bio' => str_repeat('أعمل مع مرضى السكري منذ سنوات. ', 3), 'consultation_mode' => 'online',
                'accepting_clients' => true,
            ]);
        }

        return $user;
    }

    public function test_a_nutritionist_fills_their_profile_from_the_panel(): void
    {
        $nutritionist = $this->signInAs(Access::NUTRITIONIST);

        Livewire::test(NutritionistProfilePage::class)
            ->assertSee('ملفك غير ظاهر بعد')
            ->fillForm([
                'display_name' => 'د. ريم الأحمد', 'city' => 'aleppo', 'years_experience' => 7,
                'specialties' => ['weight', 'diabetes'], 'bio' => 'قصيرة',
                'consultation_mode' => 'clinic', 'clinic_address' => '', 'accepting_clients' => true,
            ])
            ->call('save')
            ->assertHasFormErrors(['bio', 'clinic_address'])
            ->fillForm([
                'bio' => str_repeat('أساعد مراجعيّ على وجبات تناسب حياتهم اليومية. ', 2),
                'clinic_address' => 'حلب، الفرقان',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $profile = $nutritionist->fresh()->nutritionistProfile;
        $this->assertTrue($profile->isListed());
        $this->assertSame(['weight', 'diabetes'], $profile->specialties);

        $this->get(route('site.nutritionists'))->assertOk()->assertSee('د. ريم الأحمد')->assertSee('الاستشارة الأولى مجانية');
        $this->get(route('site.nutritionists.show', $profile))->assertOk()->assertSee('حلب، الفرقان');
    }

    public function test_a_brand_owner_has_no_profile_page(): void
    {
        $this->signInAs(Access::BRAND_OWNER);
        $this->get(NutritionistProfilePage::getUrl())->assertForbidden();
    }

    public function test_the_admin_has_no_profile_page_of_their_own(): void
    {
        $this->signInAs(Access::SUPER_ADMIN);
        $this->get(NutritionistProfilePage::getUrl())->assertForbidden();
    }

    public function test_a_nutritionist_opens_the_profile_page(): void
    {
        $this->signInAs(Access::NUTRITIONIST);
        $this->get(NutritionistProfilePage::getUrl())->assertOk();
    }

    public function test_only_complete_open_active_nutritionists_are_listed(): void
    {
        $listed = $this->nutritionist('a@example.com', ['display_name' => 'د. ظاهر']);
        $this->nutritionist('b@example.com', ['display_name' => 'د. مغلق', 'accepting_clients' => false]);
        $this->nutritionist('c@example.com', ['display_name' => 'د. ناقص', 'bio' => 'قصيرة']);
        $this->nutritionist('d@example.com', ['display_name' => 'د. موقوف']);
        User::where('email', 'd@example.com')->update(['is_active' => false]);
        $exRole = $this->nutritionist('e@example.com', ['display_name' => 'د. بلا دور']);
        $exRole->removeRole(Access::NUTRITIONIST);

        $page = $this->get(route('site.nutritionists'))->assertOk();
        $page->assertSee('د. ظاهر');
        foreach (['د. مغلق', 'د. ناقص', 'د. موقوف', 'د. بلا دور'] as $hidden) {
            $page->assertDontSee($hidden);
        }

        $this->get(route('site.nutritionists.show', $listed->nutritionistProfile))->assertOk();
        $hidden = NutritionistProfile::where('display_name', 'د. مغلق')->sole();
        $this->get(route('site.nutritionists.show', $hidden))->assertNotFound();
        $this->get('/nutritionists/99999')->assertNotFound();
    }

    public function test_taking_the_permission_away_hides_the_profile(): void
    {
        $user = $this->nutritionist('a@example.com', ['display_name' => 'د. ظاهر']);

        Role::findByName(Access::NUTRITIONIST)->revokePermissionTo(Access::CONSULTATIONS_PROVIDE);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->get(route('site.nutritionists'))->assertDontSee('د. ظاهر');
        $this->get(route('site.nutritionists.show', $user->nutritionistProfile))->assertNotFound();
    }

    public function test_the_directory_filters_by_specialty_and_governorate(): void
    {
        $this->nutritionist('a@example.com', ['display_name' => 'د. سكري دمشق', 'specialties' => ['diabetes'], 'city' => 'damascus']);
        $this->nutritionist('b@example.com', ['display_name' => 'د. رياضة حلب', 'specialties' => ['sports', 'weight'], 'city' => 'aleppo']);

        $this->get(route('site.nutritionists', ['specialty' => 'sports']))->assertSee('د. رياضة حلب')->assertDontSee('د. سكري دمشق');
        $this->get(route('site.nutritionists', ['city' => 'damascus']))->assertSee('د. سكري دمشق')->assertDontSee('د. رياضة حلب');
        $this->get(route('site.nutritionists', ['specialty' => 'kidney']))->assertSee('لا يوجد أخصائي بهذه الفلترة');
        $this->get(route('site.nutritionists', ['specialty' => 'nonsense']))->assertOk()->assertSee('د. سكري دمشق')->assertSee('د. رياضة حلب');
    }

    public function test_the_permission_is_listed_for_the_roles_page(): void
    {
        $this->assertArrayHasKey(Access::CONSULTATIONS_PROVIDE, Access::permissions());
        $this->assertTrue(Role::findByName(Access::NUTRITIONIST)->hasPermissionTo(Access::CONSULTATIONS_PROVIDE));
    }
}
