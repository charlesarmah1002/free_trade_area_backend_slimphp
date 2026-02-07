<?php 

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Products extends Model {
    protected $table = 'products';

    protected $fillable = [
        'name',
        'business_id',
        'price',
        'details',
        'image_url'
    ];
}