<?php

namespace App\Services\Brands;

use App\Filament\Clusters\Brands\Resources\Brands\BrandResource;
use App\Models\Brand;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * "انربط حسابك بمطعم كذا" (the owner's choice 3): a brand owner hears in the panel bell when
 * the admin links them to a restaurant, from the user's page or the brand's. Pages pass what
 * was linked before the save, and only new links are told.
 */
class BrandLinks
{
    /** @param  list<int>  $before  brand ids the user had before the save */
    public function userSaved(User $user, array $before): void
    {
        $new = $user->brands()->whereNotIn('brands.id', $before)->get();
        foreach ($new as $brand) {
            $this->tell($user, $brand);
        }
    }

    /** @param  list<int>  $before  user ids linked to the brand before the save */
    public function brandSaved(Brand $brand, array $before): void
    {
        foreach ($brand->managers()->whereNotIn('users.id', $before)->get() as $user) {
            $this->tell($user, $brand);
        }
    }

    private function tell(User $user, Brand $brand): void
    {
        if ($brand->is_platform || ! $user->is_active) {
            return;
        }

        Notification::make()
            ->title("انربط حسابك بمطعم «{$brand->name}»")
            ->body('صار فيك تعدّل معلوماته، وتضيف تصنيفاته ومنتجاته ووصفاتها.')
            ->icon('heroicon-o-building-storefront')
            ->success()
            ->actions([Action::make('open')->label('مطعمي')->url(BrandResource::getUrl('edit', ['record' => $brand]))->markAsRead()])
            ->sendToDatabase($user);
    }
}
