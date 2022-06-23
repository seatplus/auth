<?php

use Seatplus\Auth\Services\ConvertClassToPermissionStringService;
use Seatplus\Eveapi\Models\Assets\Asset;


it('converts class to permission string', function () {

    expect(ConvertClassToPermissionStringService::get(Asset::class))->toBe('assets');

});