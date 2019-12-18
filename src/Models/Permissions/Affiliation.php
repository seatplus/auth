<?php

namespace Seatplus\Auth\Models\Permissions;

use Illuminate\Database\Eloquent\Model;

class Affiliation extends Model
{
    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'allowed'   => 'object',
        'inverse'   => 'object',
        'forbidden' => 'object',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'allowed', 'inverse', 'forbidden',
    ];
}
