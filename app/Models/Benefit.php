<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Benefit extends Model
{
    use HasFactory, SoftDeletes;

    public $primaryKey      = 'type';
    public $incrementing    = false;
    public $timestamps      = false;
    protected $guarded      = [];
    protected $casts        = [
        'payload' => 'array',
        'default' => 'boolean'
    ];

    const FREE_DELIVERY = 'free_delivery';


    const TYPES = [
        self::FREE_DELIVERY
    ];
}
