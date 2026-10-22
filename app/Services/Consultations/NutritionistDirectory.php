<?php

namespace App\Services\Consultations;

use App\Enums\NutritionistSpecialty;
use App\Enums\SyrianGovernorate;
use App\Models\NutritionistProfile;
use App\Models\User;
use Illuminate\Support\Collection;

/** The nutritionists shown on the site, and saving a nutritionist's own profile. */
class NutritionistDirectory
{
    /** @return Collection<int, NutritionistProfile> listed profiles, filtered, by name */
    public function listed(?NutritionistSpecialty $specialty = null, ?SyrianGovernorate $city = null): Collection
    {
        return NutritionistProfile::query()
            ->listed()
            ->with('user')
            ->when($specialty, fn ($q) => $q->whereJsonContains('specialties', $specialty->value))
            ->when($city, fn ($q) => $q->where('city', $city->value))
            ->orderBy('display_name')
            ->get()
            // The role and permission live with the users: checked here, exactly.
            ->filter(fn (NutritionistProfile $p) => $p->isListed())
            ->values();
    }

    /** A listed profile, or null (a hidden one is "not found" for visitors). */
    public function find(int $id): ?NutritionistProfile
    {
        $profile = NutritionistProfile::query()->listed()->with('user')->find($id);

        return $profile?->isListed() ? $profile : null;
    }

    public function profileOf(User $nutritionist): NutritionistProfile
    {
        return $nutritionist->nutritionistProfile()->firstOrNew();
    }

    /** @param  array<string, mixed>  $data  validated by the panel form */
    public function save(User $nutritionist, array $data): NutritionistProfile
    {
        $profile = $this->profileOf($nutritionist)->fill($data);

        if (! $profile->consultation_mode?->needsAddress()) {
            $profile->clinic_address = null;
        }

        $profile->save();
        $nutritionist->setRelation('nutritionistProfile', $profile);

        return $profile;
    }
}
