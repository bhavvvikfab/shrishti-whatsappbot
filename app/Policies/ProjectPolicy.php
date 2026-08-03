<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Policies\Concerns\OwnsRecord;

class ProjectPolicy
{
    use OwnsRecord;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $this->ownsRecord($user, $project);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Project $project): bool
    {
        return $this->ownsRecord($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->ownsRecord($user, $project);
    }
}
