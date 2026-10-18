<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Filament\Clusters\AccessControl\Resources\Roles\Pages\EditRole;
use App\Filament\Clusters\AccessControl\Resources\Roles\RoleResource;
use App\Filament\Clusters\AccessControl\Resources\Users\Pages\CreateUser;
use App\Filament\Clusters\AccessControl\Resources\Users\Pages\EditUser;
use App\Filament\Clusters\AccessControl\Resources\Users\UserResource;
use App\Filament\Clusters\Brands\Resources\Brands\Pages\EditBrand;
use App\Models\Brand;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_the_admin_creates_a_brand_owner_linked_to_a_brand(): void
    {
        $this->signInAs();
        $brand = Brand::create(['name' => 'فلافل أبو محمد']);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'أبو محمد', 'email' => 'owner@example.test', 'password' => 'falafel2026',
                'is_active' => true, 'role' => Access::BRAND_OWNER, 'brands' => [$brand->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $owner = User::where('email', 'owner@example.test')->sole();
        $this->assertTrue($owner->hasRole(Access::BRAND_OWNER));
        $this->assertSame([$brand->id], $owner->managedBrandIds());
        $this->assertTrue(Hash::check('falafel2026', $owner->password));
    }

    public function test_weak_passwords_and_brandless_owners_are_refused(): void
    {
        $this->signInAs();

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'x', 'email' => 'x@example.test', 'password' => '1234', 'role' => Access::BRAND_OWNER, 'brands' => []])
            ->call('create')
            ->assertHasFormErrors(['password', 'brands' => 'required']);
    }

    public function test_the_admin_cannot_demote_or_deactivate_themselves(): void
    {
        $admin = $this->signInAs();

        Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->fillForm(['role' => Access::NUTRITIONIST, 'is_active' => false])
            ->call('save')
            ->assertHasFormErrors(['role', 'is_active']);

        $this->assertTrue($admin->fresh()->isSuperAdmin());
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_a_user_who_stops_being_a_brand_owner_loses_their_brands(): void
    {
        $this->signInAs();
        $brand = Brand::create(['name' => 'شاورما']);
        $owner = User::forceCreate(['name' => 'o', 'email' => 'o@example.test', 'password' => 'secret-password1', 'is_active' => true]);
        $owner->assignRole(Access::BRAND_OWNER);
        $owner->brands()->attach($brand->id);

        Livewire::test(EditUser::class, ['record' => $owner->getRouteKey()])
            ->fillForm(['role' => Access::NUTRITIONIST])
            ->call('save')
            ->assertHasNoFormErrors();

        $owner = $owner->fresh();
        $this->assertSame([Access::NUTRITIONIST], $owner->getRoleNames()->all());
        $this->assertSame([], $owner->managedBrandIds());
    }

    public function test_role_permissions_change_but_account_management_is_never_granted(): void
    {
        $this->signInAs();
        $nutritionist = Role::findByName(Access::NUTRITIONIST);
        $id = fn (string $name) => Permission::findByName($name)->id;

        // Account management is not on offer for any role, even with a forged value.
        Livewire::test(EditRole::class, ['record' => $nutritionist->getRouteKey()])
            ->fillForm(['permissions' => [$id(Access::IMPORTS_VIEW), $id(Access::USERS_MANAGE)]])
            ->call('save')
            ->assertHasFormErrors();

        Livewire::test(EditRole::class, ['record' => $nutritionist->getRouteKey()])
            ->fillForm(['permissions' => [$id(Access::IMPORTS_VIEW), $id(Access::FOODS_VIEW)]])
            ->call('save')
            ->assertHasNoFormErrors();

        $names = $nutritionist->fresh()->permissions->pluck('name')->all();
        $this->assertContains(Access::FOODS_VIEW, $names);
        $this->assertNotContains(Access::USERS_MANAGE, $names);
        $this->assertNotContains(Access::IMPORTS_REVIEW, $names);   // unticked

        // Takes effect at once for the role's users (Spatie's permission cache is cleared).
        $member = User::forceCreate(['name' => 'n', 'email' => 'n@example.test', 'password' => 'secret-password1', 'is_active' => true]);
        $member->assignRole(Access::NUTRITIONIST);
        $this->assertTrue($member->allows(Access::FOODS_VIEW));
        $this->assertFalse($member->allows(Access::IMPORTS_REVIEW));
    }

    public function test_the_super_admin_role_is_not_editable(): void
    {
        $this->signInAs();

        $this->get(RoleResource::getUrl('edit', ['record' => Role::findByName(Access::SUPER_ADMIN)]))->assertForbidden();
        $this->get(RoleResource::getUrl('edit', ['record' => Role::findByName(Access::NUTRITIONIST)]))->assertOk();
    }

    public function test_a_brand_owner_cannot_open_the_section(): void
    {
        $this->signInAs(Access::BRAND_OWNER);

        $this->get(UserResource::getUrl('index'))->assertForbidden();
        $this->get(RoleResource::getUrl('index'))->assertForbidden();
    }

    public function test_a_nutritionist_cannot_open_the_section(): void
    {
        $this->signInAs(Access::NUTRITIONIST);

        $this->get(UserResource::getUrl('index'))->assertForbidden();
        $this->get(RoleResource::getUrl('index'))->assertForbidden();
    }

    public function test_a_brand_owner_cannot_add_managers_to_their_brand(): void
    {
        $owner = $this->signInAs(Access::BRAND_OWNER);
        $brand = Brand::create(['name' => 'فلافل']);
        $owner->brands()->attach($brand->id);
        $intruder = User::forceCreate(['name' => 'i', 'email' => 'i@example.test', 'password' => 'secret-password1', 'is_active' => true]);
        $intruder->assignRole(Access::BRAND_OWNER);

        Livewire::test(EditBrand::class, ['record' => $brand->getRouteKey()])
            ->assertFormFieldHidden('managers')
            ->set('data.managers', [$owner->id, $intruder->id])
            ->call('save');

        $this->assertSame([$owner->id], $brand->managers()->pluck('users.id')->map(fn ($id) => (int) $id)->all());
    }
}
