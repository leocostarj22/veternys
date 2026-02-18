<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'product_id',
        'amount',
        'method',
        'status',
        'provider_reference',
        'meta',
        'paid_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'paid_at' => 'datetime',
    ];
}