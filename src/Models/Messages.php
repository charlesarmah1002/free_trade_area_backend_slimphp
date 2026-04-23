<?php 

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Messages extends Model {
    protected $table = 'messages';

    protected $fillable = [
        'user_id',
        'business_account_id',
        'message'
    ];
}