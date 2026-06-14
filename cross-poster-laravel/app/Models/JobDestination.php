<?php
// app/Models/JobDestination.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['job_id', 'platform_key', 'status', 'error', 'external_id'])]
class JobDestination extends Model
{
    public function job(): BelongsTo
    {
        return $this->belongsTo(CrossPostJob::class, 'job_id', 'id');
    }
}
