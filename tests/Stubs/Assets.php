<?php

namespace Seatplus\Auth\Tests\Stubs;


use Seatplus\Auth\Traits\HasAffiliated;
use Seatplus\Eveapi\Models\Assets\Asset;


class Assets extends Asset
{

    use HasAffiliated;

}