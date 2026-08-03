<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;
use App\Policies\Concerns\OwnsRecord;

class LeadPolicy
{
    use OwnsRecord;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lead $lead): bool
    {
        return $this->ownsRecord($user, $lead);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->ownsRecord($user, $lead);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $this->ownsRecord($user, $lead);
    }
}
