<?php

namespace App\Policies;

use App\Models\Approval;
use App\Models\User;

class ApprovalPolicy extends TenantPolicy
{
    /** Only the business's CEO can approve, reject, modify or retry. */
    public function decide(User $user, Approval $approval): bool
    {
        return $user->isCeo() && $this->sameBusiness($user, $approval);
    }
}
