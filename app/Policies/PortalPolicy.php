<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PortalPolicy extends AdminOnlyPolicy
{
    public function delete(User $user, Model $model): bool
    {
        return $user->isAdmin()
            && ! $model->entries()->exists()
            && ! $model->receivedEntries()->exists()
            && ! $model->walletTransactions()->exists();
    }
}
