<?php

namespace App\Filament\Clusters\Brands\Resources\Brands\Pages;

use App\Enums\BrandKind;
use App\Filament\Clusters\Brands\Resources\Brands\BrandResource;
use App\Models\Brand;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBrands extends ListRecords
{
    protected static string $resource = BrandResource::class;

    /** An owner with one restaurant goes straight to it ("مطعمي"). */
    public function mount(): void
    {
        if ($id = BrandResource::ownBrandId()) {
            $this->redirect(BrandResource::getUrl('edit', ['record' => $id]));

            return;
        }

        parent::mount();
    }

    public function getTitle(): string
    {
        return BrandResource::getNavigationLabel();
    }

    /**
     * For the admin: الكل / مطاعم / براندات, each with its count (one query for the three).
     * An owner of several sees their list as it is.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        if (BrandResource::ownedBrandIds() !== null) {
            return [];
        }

        $counts = Brand::query()->restaurants()
            ->selectRaw('kind, count(*) as n')->groupBy('kind')
            ->pluck('n', 'kind')->map(fn ($n) => (int) $n);

        $tabs = ['all' => Tab::make('الكل')->badge($counts->sum())];
        foreach (BrandKind::cases() as $kind) {
            $tabs[$kind->value] = Tab::make($kind->plural())
                ->badge($counts[$kind->value] ?? 0)
                ->badgeColor($kind->getColor())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('kind', $kind->value));
        }

        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('إضافة عميل'),
        ];
    }
}
