<?php

namespace App\Filament\Support\Concerns;

/**
 * A restaurant-side page (products, categories, recipe analysis) the admin does not see: for
 * them it does not exist, so a typed URL answers "not found" rather than "forbidden" (the
 * owner's decision 1a). Livewire boots this before any page mounts or checks its record.
 */
trait HiddenFromAdmin
{
    public function bootHiddenFromAdmin(): void
    {
        abort_if((bool) auth()->user()?->isSuperAdmin(), 404);
    }
}
