<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\OwnsRecord;

class TaskPolicy
{
    use OwnsRecord;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        return $this->ownsRecord($user, $task);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Task $task): bool
    {
        return $this->ownsRecord($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->ownsRecord($user, $task);
    }
}
