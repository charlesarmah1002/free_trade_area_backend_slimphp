<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRefreshTokens extends Model {
    protected $table = 'users_refresh_tokens';

    protected $fillable = [
        'user_id',
        'token_hash',
        'revoked'
    ];
}