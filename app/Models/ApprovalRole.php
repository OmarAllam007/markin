<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A company role that approves requests (HR manager, finance manager…) and who holds it. */
#[Fillable(['tenant_id', 'name', 'employee_id'])]
class ApprovalRole extends Model
{
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
