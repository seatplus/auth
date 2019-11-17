<?php

namespace Seatplus\Auth\Models\Settings;

use Illuminate\Database\Eloquent\Model;

class GlobalSettings extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'value'];

}
