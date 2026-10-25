<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends AdminOnlyPolicy
{
    /**
     * Admins can't delete their own account, so there's always one left.
     */
    public function delete(User $user, Model $model): bool
    {
        return $user->isAdmin() && ! $model->is($user);
    }
}
