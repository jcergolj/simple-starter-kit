<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function update(User $actor, User $user): bool
    {
        return $this->canManage($actor, $user);
    }

    public function delete(User $actor, User $user): bool
    {
        return $this->canManage($actor, $user);
    }

    public function block(User $actor, User $user): bool
    {
        return $this->canManage($actor, $user);
    }

    private function canManage(User $actor, User $user): bool
    {
        return $actor->isAdmin() && ! $user->isAdmin();
    }
}
