<?php
// app/Models/CrossPostJob.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['id', 'title', 'description', 'media_path', 'status', 'platform_options', 'scheduled_at', 'created_by'])]
class CrossPostJob extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'platform_options' => 'array',
        ];
    }

    public function destinations(): HasMany
    {
        return $this->hasMany(JobDestination::class, 'job_id', 'id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(JobLog::class, 'job_id', 'id');
    }
}
