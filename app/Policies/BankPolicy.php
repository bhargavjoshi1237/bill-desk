<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class BankPolicy extends AdminOnlyPolicy
{
    public function delete(User $user, Model $model): bool
    {
        return $user->isAdmin() && ! $model->entries()->exists() && ! $model->cards()->exists();
    }
}
