<?php

namespace Seatplus\Auth\Tests\Stubs;

use Seatplus\Auth\Traits\HasAffiliated;
use Seatplus\Eveapi\Models\Contacts\Contact as ContactOrigin;

class Contact extends ContactOrigin
{
    use HasAffiliated;
}
