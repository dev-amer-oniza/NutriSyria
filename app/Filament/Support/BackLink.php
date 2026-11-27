<?php

namespace App\Filament\Support;

use App\Filament\Clusters\Brands\Resources\Brands\BrandResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\Resource;
use Throwable;

/**
 * «رجوع إلى المنتجات» above the title of every add, edit and view page (the owners' report:
 * after writing a recipe there was no clear way back to the list). Drawn by a render hook from
 * the page's scopes, so no page needs its own button.
 */
final class BackLink
{
    /**
     * @param  array<int, string>  $scopes  the page class, then its resource
     * @return array{url: string, label: string}|null
     */
    public static function for(array $scopes): ?array
    {
        $page = $scopes[0] ?? null;
        $resource = $scopes[1] ?? null;
        if (! is_string($page) || ! is_string($resource) || ! is_subclass_of($resource, Resource::class)) {
            return null;
        }
        if (! is_subclass_of($page, CreateRecord::class) && ! is_subclass_of($page, EditRecord::class) && ! is_subclass_of($page, ViewRecord::class)) {
            return null;
        }
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
