<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CustomerPolicy extends OperationalPolicy
{
    /**
     * A customer with entries is part of the books and can't be removed.
     */
    public function delete(User $user, Model $model): bool
    {
        return $user->isAdmin() && ! $model->entries()->exists();
    }
}
