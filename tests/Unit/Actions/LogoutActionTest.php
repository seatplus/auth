<?php

use Seatplus\Auth\Http\Actions\LogoutAction;
use Seatplus\Auth\Models\User;

it('logs out the user and invalidates the session', function () {

    $test_user_id = $this->test_user->id;
    $test_user = User::find($test_user_id)->makeVisible(['remember_token']);

    $this->actingAs($test_user);

    $action = new LogoutAction;
    $response = $action();

    expect($response->getStatusCode())->toBe(302);
});
