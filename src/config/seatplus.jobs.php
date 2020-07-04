<?php

use Seatplus\Auth\Jobs\DispatchUserRoleSync;

return [
    'acl.update' => DispatchUserRoleSync::class,
];
