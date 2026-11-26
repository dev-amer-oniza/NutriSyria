<?php

namespace App\Policies;

use App\Models\PreparationIngredient;
use App\Models\User;
use App\Policies\Concerns\DeniesByDefault;

/** A preparation's line belongs to its preparation: whoever may edit it may edit its lines. */
class PreparationIngredientPolicy
{
    use DeniesByDefault;

    public function __construct(private readonly PreparationPolicy $preparations = new PreparationPolicy) {}

    public function viewAny(User $user): bool
    {
        return $this->preparations->viewAny($user);
    }

    public function view(User $user, PreparationIngredient $line): bool
    {
        $preparation = $line->loadMissing('preparation')->preparation;

        return $preparation !== null && $this->preparations->view($user, $preparation);
    }

    public function create(User $user): bool
    {
        return $this->preparations->create($user);
    }

    public function update(User $user, PreparationIngredient $line): bool
    {
        $preparation = $line->loadMissing('preparation')->preparation;

        return $preparation !== null && $this->preparations->update($user, $preparation);
    }

    public function delete(User $user, PreparationIngredient $line): bool
    {
        return $this->update($user, $line);
    }

    public function deleteAny(User $user): bool
    {
        return $this->create($user);
    }

    public function reorder(User $user): bool
    {
        return $this->create($user);
    }
}
