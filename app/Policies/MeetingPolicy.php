<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;
use App\Policies\Concerns\OwnsRecord;

class MeetingPolicy
{
    use OwnsRecord;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Meeting $meeting): bool
    {
        return $this->ownsRecord($user, $meeting);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Meeting $meeting): bool
    {
        return $this->ownsRecord($user, $meeting);
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return $this->ownsRecord($user, $meeting);
    }
}
