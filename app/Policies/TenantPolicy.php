<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Tenant isolation: a record is visible only to members of the business that owns it. */
abstract class TenantPolicy
{
    protected function sameBusiness(User $user, Model $model): bool
    {
        return $user->business_id !== null && $user->business_id === $model->getAttribute('business_id');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->sameBusiness($user, $model);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->sameBusiness($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->sameBusiness($user, $model) && in_array($user->role, [User::ROLE_CEO, User::ROLE_MANAGER], true);
    }
}
