<?php

namespace App\Filament\Support;

use App\Filament\Clusters\Brands\Resources\Brands\BrandResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\Page as ResourcePage;
use Livewire\Component;
use Throwable;

/**
 * «رجوع إلى المنتجات»: a coloured button above the title of every page that is a step inside
 * a section (add, edit, view, a client's plan, the recipe analysis, the ways to add foods),
 * the owners' report: clients could not find their way back. Drawn by a render hook, so no
 * page needs its own button. A page says where "back" is with backLink(); a resource page
 * that is not its list goes back to the list.
 */
final class BackLink
{
    /** @return array{url: string, label: string}|null */
    public static function for(?Component $page): ?array
    {
        if ($page === null) {
            return null;
        }
        if (method_exists($page, 'backLink')) {
            return $page->backLink();
        }
        if (! $page instanceof ResourcePage || $page instanceof ListRecords) {
            return null;
        }
        $resource = $page::getResource();
        // An owner of one restaurant: its list opens this very page, so there is nowhere to go back to.
        if ($resource === BrandResource::class && BrandResource::ownBrandId() !== null) {
            return null;
        }

        try {
            return ['url' => $resource::getUrl('index'), 'label' => 'رجوع إلى '.$resource::getNavigationLabel()];
        } catch (Throwable) {
            return null;   // a nested page whose list needs its parent: Filament's breadcrumbs lead there
        }
    }
}
