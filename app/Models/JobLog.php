<?php
// app/Models/JobLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['job_id', 'platform_key', 'message', 'type'])]
class JobLog extends Model
{
    public $timestamps = false;

    public function job(): BelongsTo
    {
        return $this->belongsTo(CrossPostJob::class, 'job_id', 'id');
    }
}
