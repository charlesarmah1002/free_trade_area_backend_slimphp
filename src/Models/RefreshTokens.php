<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefreshTokens extends Model {
    protected $table = 'refresh_tokens';

    protected $fillable = [
        'business_id',
        'token_hash'
    ];
}