<?php

namespace Tests\Concerns;

use App\Auth\Access;
use App\Models\ConsultationRequest;
use App\Models\NutritionistProfile;
use App\Models\User;
use App\Services\Consultations\ConsultationService;

/** A listed nutritionist, a person with a complete profile, and requests between them. */
trait ConsultationFixtures
{
    protected function nutritionist(string $email = 'reem@example.com'): NutritionistProfile
    {
        $user = User::forceCreate(['name' => $email, 'email' => $email, 'password' => 'x-secret-1', 'is_active' => true,
            'email_verified_at' => now(), 'phone' => '+96393'.random_int(1000000, 9999999)]);
        $user->assignRole(Access::NUTRITIONIST);

        return $user->nutritionistProfile()->create([
            'display_name' => 'د. '.$email, 'specialties' => ['diabetes'], 'city' => 'damascus',
            'bio' => str_repeat('أعمل مع مرضى السكري منذ سنوات. ', 3), 'consultation_mode' => 'online',
            'accepting_clients' => true,
        ]);
    }

    protected function client(string $email = 'sara@example.com', bool $withProfile = true): User
    {
        $user = User::forceCreate(['name' => 'سارة', 'email' => $email, 'password' => 'x-secret-1', 'is_active' => true, 'email_verified_at' => now()]);

        if ($withProfile) {
            // Two more templates in the nutritionist's library, unlike the four real ones (the
            // tests are about choosing one; DASH brings its own sodium rule).
            config(['nutrition_criteria.templates' => config('nutrition_criteria.templates') + [
                'mediterranean' => ['label' => 'البحر المتوسط', 'group' => 'general', 'description' => 'تجربة', 'protein_per_kg' => 1.0],
                'dash' => ['label' => 'DASH', 'group' => 'general', 'description' => 'تجربة', 'protein_per_kg' => 1.0,
                    'rules' => ['sodium_mg' => ['type' => 'fixed', 'value' => 1500]]],
            ]]);
            $user->profile()->create([
                'sex' => 'female', 'birth_year' => now()->year - 34, 'height_cm' => 165, 'weight_kg' => 78,
                'daily_life' => 'seated', 'work_days' => 7, 'goal' => 'lose', 'allergies' => ['peanuts'],
                'health_consent_at' => now(), 'completed_at' => now(),
            ]);
        }

        return $user;
    }

    protected function pending(User $client, NutritionistProfile $profile, string $whatsapp = '+963912345678'): ConsultationRequest
    {
        return app(ConsultationService::class)->request($client, $profile, 'أريد خطة لإنقاص الوزن مع السكري.', $whatsapp);
    }

    /** An accepted link. */
    protected function linked(User $client, NutritionistProfile $profile, string $whatsapp = '+963912345678'): ConsultationRequest
    {
        $request = $this->pending($client, $profile, $whatsapp);
        app(ConsultationService::class)->accept($profile->user, $request);

        return $request->refresh();
    }
}
