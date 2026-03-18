<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessRefreshTokens extends Model {
    protected $table = 'business_refresh_tokens';

    protected $fillable = [
        'business_id',
        'token_hash'
    ];
}