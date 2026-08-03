<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Policies\Concerns\OwnsRecord;

class InvoicePolicy
{
    use OwnsRecord;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->ownsRecord($user, $invoice);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->ownsRecord($user, $invoice);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->ownsRecord($user, $invoice);
    }
}
