<?php
// app/Models/Account.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['platform', 'account_name', 'platform_id', 'access_token', 'refresh_token', 'expires_at', 'linked_at'])]
class Account extends Model
{
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'linked_at' => 'datetime',
        ];
    }
}
