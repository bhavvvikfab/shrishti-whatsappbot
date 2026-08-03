<?php

namespace App\Policies;

use App\Models\Deal;
use App\Models\User;
use App\Policies\Concerns\OwnsRecord;

class DealPolicy
{
    use OwnsRecord;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Deal $deal): bool
    {
        return $this->ownsRecord($user, $deal);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Deal $deal): bool
    {
        return $this->ownsRecord($user, $deal);
    }

    public function delete(User $user, Deal $deal): bool
    {
        return $this->ownsRecord($user, $deal);
    }
}
