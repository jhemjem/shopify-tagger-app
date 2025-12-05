<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductTagAudit extends Model
{
    protected $fillable = [
        'product_id',
        'action',
        'tag',
        'status',
        'error_message',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
