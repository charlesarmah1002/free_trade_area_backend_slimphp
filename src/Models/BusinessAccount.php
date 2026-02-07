<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessAccount extends Model
{
    protected $table = 'business_accounts';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'business_name'
    ];
}