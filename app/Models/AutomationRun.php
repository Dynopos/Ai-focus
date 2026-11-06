<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['business_id', 'automation_key', 'run_date', 'status', 'note'])]
class AutomationRun extends Model
{
    protected function casts(): array
    {
        return ['run_date' => 'date'];
    }
}
