<?php

namespace App\Policies\Concerns;

use App\Auth\Access;
use App\Models\User;

/** Food database tables (foods, portions, data sources): read with foods.view, change with foods.manage. */
trait FoodDataPolicy
{
    use DeniesByDefault;

    public function viewAny(User $user): bool { return $this->canRead($user); }
    public function view(User $user, mixed $record): bool { return $this->canRead($user); }
    public function create(User $user): bool { return $user->allows(Access::FOODS_MANAGE); }
    public function update(User $user, mixed $record): bool { return $user->allows(Access::FOODS_MANAGE); }
    public function delete(User $user, mixed $record): bool { return $user->allows(Access::FOODS_MANAGE); }
    public function deleteAny(User $user): bool { return $user->allows(Access::FOODS_MANAGE); }
    public function reorder(User $user): bool { return $user->allows(Access::FOODS_MANAGE); }

    protected function canRead(User $user): bool
    {
        return $user->allows(Access::FOODS_VIEW) || $user->allows(Access::FOODS_MANAGE);
    }
}
