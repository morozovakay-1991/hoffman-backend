<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Any authenticated user may list invoices; the list itself is scoped to
     * their own records by the controller, so no per-record check applies here.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id;
    }
}
