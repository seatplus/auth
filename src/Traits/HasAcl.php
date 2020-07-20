<?php


namespace Seatplus\Auth\Traits;


use Seatplus\Auth\Models\Permissions\Role;

trait HasAcl
{
    public function getStatus(Role $role) : string
    {
        $relationship =  $role->acl_members()->whereUserId($this->id)->get();

        return $relationship->isNotEmpty() ? $relationship->first()->status : '';
    }

}
