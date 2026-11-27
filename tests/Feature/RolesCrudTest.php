<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Filament\Clusters\AccessControl\Resources\Permissions\Pages\ManagePermissions;
use App\Filament\Clusters\AccessControl\Resources\Roles\Pages\CreateRole;
use App\Filament\Clusters\AccessControl\Resources\Roles\Pages\EditRole;
use App\Filament\Clusters\AccessControl\Resources\Roles\Pages\ListRoles;
use App\Filament\Clusters\AccessControl\Resources\Roles\RoleResource;
use App\Models\Brand;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Roles and permissions from the panel (the owner's decisions 1a 2a 3a), and the new built-in
 * "مراجع الوصفات والأغذية" who reviews recipes instead of the nutritionist (1a 2a 3a).
 */
class RolesCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_reviewer_reviews_and_the_nutritionist_keeps_consultations_only(): void
    {
        $reviewer = Role::findByName(Access::REVIEWER);
        $this->assertSame('مراجع الوصفات والأغذية', $reviewer->displayName());
        $this->assertEqualsCanonicalizing(
            [Access::IMPORTS_VIEW, Access::IMPORTS_REVIEW, Access::PRODUCTS_VIEW_ALL, Access::NUTRITION_APPROVE],
            $reviewer->permissions->pluck('name')->all());
        $this->assertSame([Access::CONSULTATIONS_PROVIDE], Role::findByName(Access::NUTRITIONIST)->permissions->pluck('name')->all());
    }

    public function test_the_reviewer_approves_not_the_nutritionist(): void
    {
        $reviewer = $this->user(Access::REVIEWER);
        $nutritionist = $this->user(Access::NUTRITIONIST);
        $product = Product::create(['brand_id' => Brand::create(['name' => 'مطعم'])->id, 'name' => 'كرالا']);
        ProductRecipe::create(['product_id' => $product->id, 'servings' => 1]);
        $this->assertTrue($reviewer->can('approveNutrition', $product));
        $this->assertFalse($nutritionist->can('approveNutrition', $product));
    }

    public function test_the_admin_adds_a_role_and_gives_it_to_a_user(): void
    {
        $this->signInAs(Access::SUPER_ADMIN);
        Livewire::test(CreateRole::class)
            ->fillForm(['label' => 'مدخل بيانات', 'permissions' => [Permission::findByName(Access::FOODS_VIEW)->id, Permission::findByName(Access::FOODS_MANAGE)->id]])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::query()->where('label', 'مدخل بيانات')->sole();
        $this->assertStringStartsWith('custom-', $role->name);
        $this->assertEqualsCanonicalizing([Access::FOODS_VIEW, Access::FOODS_MANAGE], $role->permissions->pluck('name')->all());
        $this->assertSame('مدخل بيانات', Access::roles()[$role->name]);

        // Given to someone, it opens the panel with exactly those rights.
        $user = $this->user($role->name);
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));
        $this->assertTrue($user->allows(Access::FOODS_MANAGE));
        $this->assertFalse($user->allows(Access::NUTRITION_APPROVE));
    }

    public function test_a_role_name_is_unique(): void
    {
        $this->signInAs(Access::SUPER_ADMIN);
        Livewire::test(CreateRole::class)
            ->fillForm(['label' => 'مراجع الوصفات والأغذية'])
            ->call('create')
            ->assertHasFormErrors(['label' => 'unique']);
    }

    public function test_a_built_in_role_is_renamed_but_never_deleted(): void
    {
        $admin = $this->signInAs(Access::SUPER_ADMIN);
        $reviewer = Role::findByName(Access::REVIEWER);

        Livewire::test(EditRole::class, ['record' => $reviewer->getRouteKey()])
            ->fillForm(['label' => 'مراجع البيانات'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('مراجع البيانات', $reviewer->refresh()->displayName());
        $this->assertSame(Access::REVIEWER, $reviewer->name);

        $this->assertFalse($admin->can('delete', $reviewer));
        $this->assertFalse($admin->can('update', Role::findByName(Access::SUPER_ADMIN)));
    }

    public function test_a_role_with_users_is_not_deleted(): void
    {
        $this->signInAs(Access::SUPER_ADMIN);
        $role = Role::create(['name' => 'custom-test', 'label' => 'مدخل بيانات', 'guard_name' => 'web']);
        $this->user($role->name);

        Livewire::test(ListRoles::class)
            ->callTableAction('delete', $role)
            ->assertNotified('ما فيك تحذفه هلق');
        $this->assertModelExists($role);

        User::query()->whereHas('roles', fn ($q) => $q->where('name', 'custom-test'))->get()->each->syncRoles([]);
        Livewire::test(ListRoles::class)->callTableAction('delete', $role);
        $this->assertModelMissing($role);
    }

    public function test_a_permission_is_renamed_but_not_added_or_deleted(): void
    {
        $admin = $this->signInAs(Access::SUPER_ADMIN);
        $permission = Permission::findByName(Access::FOODS_VIEW);

        Livewire::test(ManagePermissions::class)
            ->assertCanSeeTableRecords([$permission])
            ->callTableAction('edit', $permission, ['label' => 'شوف الأغذية', 'description' => 'قائمة الأغذية للقراءة'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('شوف الأغذية', Access::permissionLabels()[Access::FOODS_VIEW]);
        $this->assertSame('قائمة الأغذية للقراءة', Access::permissionDescription(Access::FOODS_VIEW));
        $this->assertFalse($admin->can('create', Permission::class));
        $this->assertFalse($admin->can('delete', $permission));
    }

    public function test_only_the_admin_opens_the_roles_page(): void
    {
        $this->signInAs(Access::REVIEWER);
        $this->get(RoleResource::getUrl('index'))->assertForbidden();
    }

    private function user(string $role): User
    {
        $user = User::forceCreate(['name' => $role, 'email' => $role.'-'.uniqid().'@example.test', 'password' => 'secret-password', 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
