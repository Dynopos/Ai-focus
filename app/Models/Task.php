<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'project_id', 'contact_id', 'title', 'description', 'priority', 'assigned_to', 'assignee_name',
    'deadline', 'status', 'revenue_potential', 'financial_impact', 'strategic_importance', 'source',
    'completion_evidence', 'ceo_approval_status', 'dedupe_key', 'completed_at',
])]
class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'source' => TaskSource::class,
            'deadline' => 'datetime',
            'completed_at' => 'datetime',
            'revenue_potential' => 'decimal:2',
            'financial_impact' => 'decimal:2',
            'strategic_importance' => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class)->latest('id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', TaskStatus::openValues());
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereNotNull('deadline')->where('deadline', '<', now());
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->deadline !== null && $this->deadline->isPast();
    }

    public function assigneeLabel(): string
    {
        return $this->assignee?->name ?? $this->assignee_name ?? 'Unassigned';
    }

    public function reference(): string
    {
        return 'T-'.$this->id;
    }
}
