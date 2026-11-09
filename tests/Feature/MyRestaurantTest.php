<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Filament\Clusters\Brands\Resources\Brands\BrandResource;
use App\Filament\Clusters\Brands\Resources\Brands\Pages\ListBrands;
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

        $this->assertSame('البراندات والمنتجات', \App\Filament\Clusters\Brands\BrandsCluster::getNavigationLabel());
    }

    public function test_the_admin_keeps_the_list_of_brands(): void
    {
        Brand::create(['name' => 'بيت المجدرة']);
        $this->signInAs();

        $this->assertSame('البراندات', BrandResource::getNavigationLabel());
        $this->assertSame(BrandResource::getUrl('index'), BrandResource::getNavigationUrl());
        Livewire::test(ListBrands::class)->assertNoRedirect();
    }
}
