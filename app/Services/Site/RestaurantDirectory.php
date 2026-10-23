<?php

namespace App\Services\Site;

use App\Data\Nutrition\MealFacts;
use App\Data\Site\RestaurantFacts;
use App\Http\Requests\Auth\AuthFormRequest;
use App\Models\Brand;
use App\Models\Product;
use App\Services\Import\ArabicNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * The restaurants (active brands) on the public site: the list with its search and
 * "delivers" filter, one restaurant's details, and its products. Built once per catalog
 * version (PublicCatalogCache) in a fixed number of queries, whatever the number of brands.
 */
class RestaurantDirectory
{
    public function __construct(
        private readonly PublicCatalogCache $cache,
        private readonly MealCatalog $meals,
    ) {}

    /**
     * Restaurants with the most approved meals first, then by name.
     *
     * @return list<RestaurantFacts>
     */
    public function all(?string $search = null, bool $deliveryOnly = false, ?int $limit = null): array
    {
        $restaurants = $this->cache->remember('restaurants', fn () => $this->build());

        if (filled($search)) {
            $term = ArabicNormalizer::normalize($search);
            $restaurants = array_filter($restaurants, fn (RestaurantFacts $r) => str_contains(ArabicNormalizer::normalize($r->name), $term));
        }
        if ($deliveryOnly) {
            $restaurants = array_filter($restaurants, fn (RestaurantFacts $r) => $r->hasDelivery);
        }

        $restaurants = array_values($restaurants);

        return $limit ? array_slice($restaurants, 0, $limit) : $restaurants;
    }

    public function find(int $id): ?RestaurantFacts
    {
        foreach ($this->all() as $restaurant) {
            if ($restaurant->id === $id) {
                return $restaurant;
            }
        }

        return null;
    }

    /**
     * Every active product of a restaurant: the approved ones with their values, and the
     * rest by name only, "values under review" (the owner's decision 2a).
     *
     * @return array{meals: list<MealFacts>, pending: list<array{id: int, name: string, description: ?string, image: ?string}>}
     */
    public function menu(int $brandId): array
    {
        $meals = $this->meals->ofBrand($brandId);
        $approved = array_column(array_map(fn (MealFacts $m) => ['id' => $m->id], $meals), 'id');

        $pending = $this->cache->remember("restaurant.{$brandId}.pending", fn () => Product::query()
            ->where('brand_id', $brandId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'img'])
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'image' => $p->img ? Storage::disk('public')->url($p->img) : null,
            ])
            ->all());

        return [
            'meals' => $meals,
            'pending' => array_values(array_filter($pending, fn (array $p) => ! in_array($p['id'], $approved, true))),
        ];
    }

    /** @return list<RestaurantFacts> */
    private function build(): array
    {
        $mealCounts = array_count_values(array_map(fn (MealFacts $m) => $m->brandId, $this->meals->meals()));

        return Brand::query()
            ->where('is_active', true)
            ->withCount(['products' => fn (Builder $q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get()
            ->map(fn (Brand $brand) => $this->facts($brand)->withMealCount($mealCounts[$brand->id] ?? 0))
            ->sortByDesc(fn (RestaurantFacts $r) => $r->mealCount)
            ->values()
            ->all();
    }

    private function facts(Brand $brand): RestaurantFacts
    {
        $phones = array_values(array_filter(array_map(function (?string $phone) {
            $number = AuthFormRequest::normalizeWhatsapp($phone);

            return $number ? ['label' => trim((string) $phone), 'href' => 'tel:'.$number] : null;
        }, [$brand->phone_1, $brand->phone_2])));
        $whatsapp = AuthFormRequest::normalizeWhatsapp($brand->whatsapp);

        return new RestaurantFacts(
            id: $brand->id,
            name: $brand->name,
            description: $brand->description,
            logo: $brand->logo ? Storage::disk('public')->url($brand->logo) : null,
            city: $brand->city?->label(),
            address: $brand->address,
            openingHours: $brand->opening_hours,
            phones: $phones,
            whatsappUrl: $whatsapp ? 'https://wa.me/'.ltrim($whatsapp, '+') : null,
            instagramUrl: $brand->instagram_url,
            facebookUrl: $brand->facebook_url,
            hasDelivery: $brand->has_delivery,
            deliveryNote: $brand->has_delivery ? $brand->delivery_note : null,
            productCount: (int) $brand->products_count,
        );
    }
}
