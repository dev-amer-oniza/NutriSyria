<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Filament\Clusters\Brands\Resources\Brands\BrandResource;
use App\Filament\Clusters\Brands\Resources\Brands\Pages\ListBrands;
use App\Filament\Clusters\Brands\Resources\Categories\Pages\ManageProductCategories;
use App\Filament\Clusters\Brands\Resources\Categories\ProductCategoryResource;
use App\Models\ProductCategory;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** A brand owner manages "مطعمي", not a list of brands (the owner's decisions 1a 2a 3a). */
class MyRestaurantTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_owner_of_one_restaurant_goes_straight_to_it(): void
    {
        $brand = Brand::create(['name' => 'بيت المجدرة']);
        $this->signInAs(Access::BRAND_OWNER)->brands()->attach($brand->id);
        $edit = BrandResource::getUrl('edit', ['record' => $brand]);

        $this->assertSame('مطعمي', BrandResource::getNavigationLabel());
        $this->assertSame($edit, BrandResource::getNavigationUrl());
        Livewire::test(ListBrands::class)->assertRedirect($edit);

        $this->assertSame('مطعمي ومنتجاته', \App\Filament\Clusters\Brands\BrandsCluster::getNavigationLabel());
        $this->get($edit)->assertOk()->assertSee('مطعمي')->assertSee('التصنيفات')->assertSee('المنتجات');
    }

    public function test_an_owner_not_yet_linked_is_told_why_the_page_is_empty(): void
    {
        Brand::create(['name' => 'مطعم غيره']);
        $this->signInAs(Access::BRAND_OWNER);

        $this->get(BrandResource::getUrl('index'))->assertOk()
            ->assertSee('لم يُربط حسابك بمطعمك بعد')
            ->assertDontSee('مطعم غيره');
    }

    public function test_an_owner_of_several_sees_only_theirs(): void
    {
        $mine = [Brand::create(['name' => 'فرع المزة']), Brand::create(['name' => 'فرع المالكي'])];
        Brand::create(['name' => 'مطعم غيره']);
        $this->signInAs(Access::BRAND_OWNER)->brands()->attach(array_map(fn (Brand $b) => $b->id, $mine));

        $this->assertSame('مطاعمي', BrandResource::getNavigationLabel());
        Livewire::test(ListBrands::class)->assertNoRedirect()->assertCanSeeTableRecords($mine)->assertCountTableRecords(2);
    }

    public function test_the_nutritionist_keeps_the_general_section_name(): void
    {
        $this->signInAs(Access::NUTRITIONIST);

        $this->assertSame('منتجات المطاعم', \App\Filament\Clusters\Brands\BrandsCluster::getNavigationLabel());
    }

    public function test_the_admin_keeps_the_list_of_brands(): void
    {
        Brand::create(['name' => 'بيت المجدرة']);
        $this->signInAs();

        $this->assertSame('البراندات', BrandResource::getNavigationLabel());
        $this->assertSame(BrandResource::getUrl('index'), BrandResource::getNavigationUrl());
        Livewire::test(ListBrands::class)->assertNoRedirect();
    }

    public function test_my_restaurant_is_its_details_and_categories_have_their_own_page(): void
    {
        $brand = Brand::create(['name' => 'بيت المجدرة']);
        $other = Brand::create(['name' => 'مطعم غيره']);
        $this->signInAs(Access::BRAND_OWNER)->brands()->attach($brand->id);

        // "مطعمي": no products or categories tabs (each has its own page beside it).
        $this->assertSame([], BrandResource::getRelations());
        $this->get(ProductCategoryResource::getUrl())->assertOk();

        // One restaurant: the section is added to it without picking it.
        Livewire::test(ManageProductCategories::class)
            ->callAction(TestAction::make(CreateAction::class), ['name' => 'مقبلات'])
            ->assertHasNoActionErrors()
            ->callAction(TestAction::make(CreateAction::class), ['name' => 'مقبلات'])
            ->assertHasActionErrors(['name' => 'unique']);
        $this->assertSame($brand->id, ProductCategory::where('name', 'مقبلات')->value('brand_id'));

        // Another restaurant's sections are not listed, and cannot be the target.
        $theirs = new ProductCategory(['name' => 'مشاوي']);
        $theirs->brand_id = $other->id;
        $theirs->save();
        Livewire::test(ManageProductCategories::class)
            ->assertCanNotSeeTableRecords([$theirs])
            // A forged brand is ignored: the hidden field always means their own restaurant.
            ->callAction(TestAction::make(CreateAction::class), ['brand_id' => $other->id, 'name' => 'حلويات']);
        $this->assertSame($brand->id, ProductCategory::where('name', 'حلويات')->value('brand_id'));
    }

    public function test_an_owner_of_several_cannot_add_a_section_to_another_brand(): void
    {
        $mine = [Brand::create(['name' => 'فرع المزة']), Brand::create(['name' => 'فرع المالكي'])];
        $other = Brand::create(['name' => 'مطعم غيره']);
        $this->signInAs(Access::BRAND_OWNER)->brands()->attach(array_map(fn (Brand $b) => $b->id, $mine));

        Livewire::test(ManageProductCategories::class)
            ->callAction(TestAction::make(CreateAction::class), ['brand_id' => $other->id, 'name' => 'حلويات'])
            ->assertHasActionErrors(['brand_id']);
        Livewire::test(ManageProductCategories::class)
            ->callAction(TestAction::make(CreateAction::class), ['brand_id' => $mine[1]->id, 'name' => 'حلويات'])
            ->assertHasNoActionErrors();
        $this->assertSame([$mine[1]->id], ProductCategory::where('name', 'حلويات')->pluck('brand_id')->all());
    }

    public function test_the_admin_has_no_categories_page(): void
    {
        $this->signInAs();

        $this->assertSame([], BrandResource::getRelations());
        $this->assertSame('البراندات', \App\Filament\Clusters\Brands\BrandsCluster::getNavigationLabel());
        $this->get(ProductCategoryResource::getUrl())->assertNotFound();
    }
}
