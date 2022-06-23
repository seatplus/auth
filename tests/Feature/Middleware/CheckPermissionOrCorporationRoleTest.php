<?php

use Seatplus\Auth\Http\Middleware\CheckPermissionOrCorporationRole;
use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Eveapi\Models\Character\CharacterRole;

beforeEach(function () {
    test()->role = Role::create(['name' => faker()->name]);
    test()->permission = Permission::create(['name' => faker()->streetName()]);

    $permission = test()->permission->name;

    Route::middleware([CheckPermissionOrCorporationRole::class . ":$permission,Accountant"])
        ->prefix('test')
        ->get('/', function () {
            return 'test';
        })->name('test');

});

it('returns a 401 if user is not authenticated', function () {

    $response = $this->get(route('test'));
    $response->assertStatus(401);
});

it('returns a 200 if user has permission', function (string $permission) {

    test()->actingAs(test()->test_user);
    test()->assignPermissionToTestUser($permission);

    $response = $this->get(route('test'));
    $response->assertStatus(200);

})->with([
    'superuser' => 'superuser',
    'accountant' => fn() => test()->permission->name,
]);

it('return a 200 if user has corporation_role', function (string $corporation_role) {

    test()->actingAs(test()->test_user);
    CharacterRole::query()->delete();

    CharacterRole::factory()->create([
        'character_id' => test()->test_character->character_id,
        'roles' => [$corporation_role],
    ]);

    $response = $this->get(route('test'));
    $response->assertStatus(200);
})->with([
    'Accountant' => 'Accountant',
    'Director' => 'Director',
]);

it('return a 401 if user is missing corporation_role', function () {

    test()->actingAs(test()->test_user);
    CharacterRole::query()->delete();

    $response = $this->get(route('test'));
    $response->assertStatus(401);
});


