<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, mixed $model): bool
    {
        if (!$model instanceof Customer) {
            return false;
        }

        return Customer::query()
            ->visibleToUser($user)
            ->whereKey($model->getKey())
            ->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, mixed $model): bool
    {
        if (!$model instanceof Customer) {
            return false;
        }

        return $user->isAdmin() || $model->isOwnedBy($user);
    }

    public function delete(User $user, mixed $model): bool
    {
        return $this->update($user, $model);
    }
}
