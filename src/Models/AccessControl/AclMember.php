<?php


namespace Seatplus\Auth\Models\AccessControl;


use Illuminate\Database\Eloquent\Model;

class AclMember extends Model
{
    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

}
