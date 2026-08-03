<?php

namespace App\Policies;

use App\Models\FollowUp;
use App\Models\User;
use App\Policies\Concerns\OwnsRecord;

class FollowUpPolicy
{
    use OwnsRecord;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FollowUp $followUp): bool
    {
        return $this->ownsRecord($user, $followUp);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, FollowUp $followUp): bool
    {
        return $this->ownsRecord($user, $followUp);
    }

    public function delete(User $user, FollowUp $followUp): bool
    {
        return $this->ownsRecord($user, $followUp);
    }
}
