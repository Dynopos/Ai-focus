<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['category', 'title', 'content', 'is_verified', 'source', 'created_by'])]
class Memory extends Model
{
    use SoftDeletes;

    public const CATEGORIES = [
        'company_profile' => 'Company profile',
        'goal' => 'Business goal',
        'project' => 'Key project',
        'customer' => 'Customer follow-up',
        'decision' => 'Previous decision',
        'commitment' => 'Existing commitment',
        'priority' => 'CEO priority',
        'outcome' => 'Historical outcome',
    ];

    protected function casts(): array
    {
        return ['is_verified' => 'boolean'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
