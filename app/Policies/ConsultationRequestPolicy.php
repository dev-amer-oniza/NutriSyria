<?php

namespace App\Policies;

use App\Models\ConsultationRequest;
use App\Models\NutritionistProfile;
use App\Models\User;
use App\Policies\Concerns\DeniesByDefault;

/**
 * Consultation requests in the panel: a nutritionist sees and answers only the requests sent
 * to them. Nobody else (the admin included) reads them: they carry a person's reason and,
 * once accepted, their contact. People manage their own requests on the site.
 */
class ConsultationRequestPolicy
{
    use DeniesByDefault;

    public function viewAny(User $user): bool
    {
        return NutritionistProfile::eligible($user);
    }

    public function view(User $user, ConsultationRequest $request): bool
    {
        return $this->viewAny($user) && $request->nutritionist_id === $user->getKey();
    }

    /** Accept or decline. */
    public function respond(User $user, ConsultationRequest $request): bool
    {
        return $this->view($user, $request) && $request->isPending();
    }
}
