<?php

use Illuminate\Support\Facades\Route;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use Seatplus\Auth\Http\Middleware\CheckPermissionAndAffiliation;
use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Character\CharacterRole;

beforeEach(function () {
    test()->role = Role::create(['name' => faker()->name]);
    test()->permission = Permission::create(['name' => faker()->streetName()]);

    test()->role->givePermissionTo(test()->permission);
    test()->role->activateMember(test()->test_user);

    $permission = test()->permission->name;

    Route::middleware([CheckPermissionAndAffiliation::class . ":$permission"])
        ->prefix('character')
        ->name('character.')
        ->group(function () {
            Route::post('/test/', fn () => response('Hello World'))->name('post');
            Route::get('/character/{character_id}/', fn (int $character_id) => response('Hello World'))->name('character');
            Route::get('/corporation/{corporation_id}/', fn (int $corporation_id) => response('Hello World'))->name('corporation');
            Route::get('/alliance/{alliance_id}/', fn (int $alliance_id) => response('Hello World'))->name('alliance');
            Route::get('/character_ids', fn () => response('Hello World'))->name('character_ids');
            Route::get('/corporation_ids', fn () => response('Hello World'))->name('corporation_ids');
            Route::get('/alliance_ids', fn () => response('Hello World'))->name('alliance_ids');
        });

    Route::middleware([CheckPermissionAndAffiliation::class . ":$permission,Director"])
        ->prefix('corporation')
        ->name('corporation.')
        ->group(function () {
            Route::post('/test/', fn () => response('Hello World'))->name('post');
            Route::get('/character/{character_id}/', fn (int $character_id) => response('Hello World'))->name('character');
            Route::get('/corporation/{corporation_id}/', fn (int $corporation_id) => response('Hello World'))->name('corporation');
            Route::get('/alliance/{alliance_id}/', fn (int $alliance_id) => response('Hello World'))->name('alliance');
            Route::get('/character_ids', fn () => response('Hello World'))->name('character_ids');
            Route::get('/corporation_ids', fn () => response('Hello World'))->name('corporation_ids');
            Route::get('/alliance_ids', fn () => response('Hello World'))->name('alliance_ids');
        });

    test()->secondary_character = CharacterInfo::factory()->create();
});

it('it validates parameters for superuser', function (string $method, string $route, int|array $route_param, string $status = 'ok') {
    assignPermissionToTestUser(['superuser']);

    test()->actingAs(test()->test_user);

    $response = match ($method) {
        'post' => post(route($route, $route_param)),
        'get' => get(route($route, $route_param))
    };

    match ($status) {
        'forbidden' => $response->assertForbidden(), //403
        'unauthorized' => $response->assertUnauthorized(), //401
        'ok' => $response->assertOk()
    };
})
    ->with([
        // Character
        ['post', 'character.post', fn () => ['character_id' => test()->test_character->character_id]],
        ['post', 'character.post', fn () => ['corporation_id' => test()->test_character->corporation->corporation_id]],
        ['post', 'character.post', fn () => ['alliance_id' => test()->test_character->alliance->alliance_id]],
        ['get', 'character.character', fn () => test()->test_character->character_id],
        ['get', 'character.corporation', fn () => test()->test_character->corporation->corporation_id],
        ['get', 'character.alliance', fn () => test()->test_character->alliance->alliance_id],
        ['get', 'character.character_ids', fn () => ['character_ids' => []], 'forbidden'],
        ['get', 'character.corporation_ids', fn () => ['corporation_ids' => []], 'forbidden'],
        ['get', 'character.alliance_ids', fn () => ['alliance_ids' => []], 'forbidden'],
        ['get', 'character.character_ids', fn () => ['character_ids' => [test()->test_character->character_id]]],
        ['get', 'character.corporation_ids', fn () => ['corporation_ids' => [test()->test_character->corporation->corporation_id]]],
        ['get', 'character.corporation_ids', fn () => ['alliance_ids' => [test()->test_character->alliance->alliance_id]]],
        // Corporation Role
        ['post', 'corporation.post', fn () => ['character_id' => test()->test_character->character_id]],
        ['post', 'corporation.post', fn () => ['corporation_id' => test()->test_character->corporation->corporation_id]],
        ['post', 'corporation.post', fn () => ['alliance_id' => test()->test_character->alliance->alliance_id]],
        ['get', 'corporation.character', fn () => test()->test_character->character_id],
        ['get', 'corporation.corporation', fn () => test()->test_character->corporation->corporation_id],
        ['get', 'corporation.alliance', fn () => test()->test_character->alliance->alliance_id],
        ['get', 'corporation.character_ids', fn () => ['corporation_ids' => []], 'forbidden'],
        ['get', 'corporation.corporation_ids', fn () => ['corporation_ids' => []], 'forbidden'],
        ['get', 'corporation.alliance_ids', fn () => ['alliance_ids' => []], 'forbidden'],
        ['get', 'corporation.character_ids', fn () => ['corporation_ids' => [test()->test_character->character_id]]],
        ['get', 'corporation.corporation_ids', fn () => ['corporation_ids' => [test()->test_character->corporation->corporation_id]]],
        ['get', 'corporation.alliance_ids', fn () => ['alliance_ids' => [test()->test_character->alliance->alliance_id]]],
]);

it('checks owned character ids', function (string $method, string $route, array|int $route_param, string $status = 'ok') {
    expect(test()->test_user->can('superuser'))->toBeFalse();

    test()->actingAs(test()->test_user);

    $response = match ($method) {
        'post' => post(route($route, $route_param)),
        'get' => get(route($route, $route_param))
    };

    match ($status) {
        'forbidden' => $response->assertForbidden(), //403
        'unauthorized' => $response->assertUnauthorized(), //401
        'ok' => $response->assertOk()
    };
})
    ->with([
        ['post', 'character.post', fn () => ['character_id' => test()->test_character->character_id]],
        ['post', 'character.post', fn () => ['corporation_id' => test()->test_character->corporation->corporation_id], 'unauthorized'],
        ['post', 'character.post', fn () => ['alliance_id' => test()->test_character->alliance->alliance_id], 'unauthorized'],
        ['get', 'character.character', fn () => test()->test_character->character_id],
        ['get', 'character.corporation', fn () => test()->test_character->corporation->corporation_id, 'unauthorized'],
        ['get', 'character.alliance', fn () => test()->test_character->alliance->alliance_id, 'unauthorized'],
        ['get', 'character.character_ids', fn () => ['character_ids' => []], 'forbidden'],
        ['get', 'character.corporation_ids', fn () => ['corporation_ids' => []], 'forbidden'],
        ['get', 'character.alliance_ids', fn () => ['alliance_ids' => []], 'forbidden'],
        ['get', 'character.character_ids', fn () => ['character_ids' => [test()->test_character->character_id]]],
        ['get', 'character.corporation_ids', fn () => ['corporation_ids' => [test()->test_character->corporation->corporation_id]], 'unauthorized'],
        ['get', 'character.corporation_ids', fn () => ['alliance_ids' => [test()->test_character->alliance->alliance_id]], 'unauthorized'],
        // Corporation Role
        ['post', 'corporation.post', fn () => ['character_id' => test()->test_character->character_id]],
        ['post', 'corporation.post', fn () => ['corporation_id' => test()->test_character->corporation->corporation_id], 'unauthorized'],
        ['post', 'corporation.post', fn () => ['alliance_id' => test()->test_character->alliance->alliance_id], 'unauthorized'],
        ['get', 'corporation.character', fn () => test()->test_character->character_id],
        ['get', 'corporation.corporation', fn () => test()->test_character->corporation->corporation_id, 'unauthorized'],
        ['get', 'corporation.alliance', fn () => test()->test_character->alliance->alliance_id, 'unauthorized'],
        ['get', 'corporation.character_ids', fn () => ['corporation_ids' => []], 'forbidden'],
        ['get', 'corporation.corporation_ids', fn () => ['corporation_ids' => []], 'forbidden'],
        ['get', 'corporation.alliance_ids', fn () => ['alliance_ids' => []], 'forbidden'],
        ['get', 'corporation.character_ids', fn () => ['corporation_ids' => [test()->test_character->character_id]]],
        ['get', 'corporation.corporation_ids', fn () => ['corporation_ids' => [test()->test_character->corporation->corporation_id]], 'unauthorized'],
        ['get', 'corporation.alliance_ids', fn () => ['alliance_ids' => [test()->test_character->alliance->alliance_id]], 'unauthorized'],
]);

it('checks owned corporation id', function (string $method, string $route, array|int $route_param) {
    expect(test()->test_user->can('superuser'))->toBeFalse();

    CharacterRole::factory()->create([
        'character_id' => test()->test_character->character_id,
        'roles' => ['Director'],
    ]);

    test()->actingAs(test()->test_user);

    match ($method) {
        'post' => post(route($route, $route_param))->assertOk(),
        'get' => get(route($route, $route_param))->assertOk()
    };
})
    ->with([
    ['post', 'corporation.post', fn () => ['corporation_id' => test()->test_character->corporation->corporation_id]],
    ['get', 'corporation.corporation_ids', fn () => ['character_ids' => [test()->test_character->corporation->corporation_id]]],
    ['get', 'corporation.corporation', fn () => test()->test_character->corporation->corporation_id],
]);

it('checks affiliated ids', function (string $method, string $route, array|int $route_param, string $status = 'ok') {
    expect(test()->test_user->can('superuser'))->toBeFalse();

    test()->createAffiliation(
        test()->role,
        test()->secondary_character->alliance->alliance_id,
        \Seatplus\Eveapi\Models\Alliance\AllianceInfo::class,
        'allowed'
    );

    test()->actingAs(test()->test_user);

    $response = match ($method) {
        'post' => post(route($route), $route_param),
        'get' => get(route($route, $route_param))
    };

    match ($status) {
        'forbidden' => $response->assertForbidden(), //403
        'unauthorized' => $response->assertUnauthorized(), //401
        'ok' => $response->assertOk()
    };
})
    ->with([
        ['post', 'character.post', fn () => ['character_id' => test()->secondary_character->character_id]],
        ['post', 'character.post', fn () => ['corporation_id' => test()->secondary_character->corporation->corporation_id]],
        ['post', 'character.post', fn () => ['alliance_id' => test()->secondary_character->alliance->alliance_id]],
        ['get', 'character.character', fn () => test()->secondary_character->character_id],
        ['get', 'character.corporation', fn () => test()->secondary_character->corporation->corporation_id],
        ['get', 'character.alliance', fn () => test()->secondary_character->alliance->alliance_id],
        ['get', 'character.character_ids', fn () => ['character_ids' => []], 'forbidden'],
        ['get', 'character.corporation_ids', fn () => ['corporation_ids' => []], 'forbidden'],
        ['get', 'character.alliance_ids', fn () => ['alliance_ids' => []], 'forbidden'],
        ['get', 'character.character_ids', fn () => ['character_ids' => [test()->secondary_character->character_id]]],
        ['get', 'character.corporation_ids', fn () => ['corporation_ids' => [test()->secondary_character->corporation->corporation_id]]],
        ['get', 'character.corporation_ids', fn () => ['alliance_ids' => [test()->secondary_character->alliance->alliance_id]]],
        // Corporation Role
        ['post', 'corporation.post', fn () => ['character_id' => test()->secondary_character->character_id]],
        ['post', 'corporation.post', fn () => ['corporation_id' => test()->secondary_character->corporation->corporation_id]],
        ['post', 'corporation.post', fn () => ['alliance_id' => test()->secondary_character->alliance->alliance_id]],
        ['get', 'corporation.character', fn () => test()->secondary_character->character_id],
        ['get', 'corporation.corporation', fn () => test()->secondary_character->corporation->corporation_id],
        ['get', 'corporation.alliance', fn () => test()->secondary_character->alliance->alliance_id],
        ['get', 'corporation.character_ids', fn () => ['corporation_ids' => []], 'forbidden'],
        ['get', 'corporation.corporation_ids', fn () => ['corporation_ids' => []], 'forbidden'],
        ['get', 'corporation.alliance_ids', fn () => ['alliance_ids' => []], 'forbidden'],
        ['get', 'corporation.character_ids', fn () => ['corporation_ids' => [test()->secondary_character->character_id]]],
        ['get', 'corporation.corporation_ids', fn () => ['corporation_ids' => [test()->secondary_character->corporation->corporation_id]]],
        ['get', 'corporation.alliance_ids', fn () => ['alliance_ids' => [test()->secondary_character->alliance->alliance_id]]],
    ]);

it('returns unauthorized for non affiliated ids', function (string $method, string $route, array|int $route_param, string $status = 'ok') {
    expect(test()->test_user->can('superuser'))->toBeFalse();

    test()->createAffiliation(
        test()->role,
        test()->secondary_character->character_id,
        CharacterInfo::class,
        'forbidden'
    );

    test()->actingAs(test()->test_user);

    $response = match ($method) {
        'post' => post(route($route), $route_param),
        'get' => get(route($route, $route_param))
    };

    match ($status) {
        'forbidden' => $response->assertForbidden(), //403
        'unauthorized' => $response->assertUnauthorized(), //401
        'ok' => $response->assertOk()
    };
})
    ->with([
        // POST
        ['post', 'character.post', fn () => ['character_id' => test()->secondary_character->character_id], 'unauthorized'],
        ['post', 'character.post', fn () => ['character_id' => test()->test_character->character_id], 'ok'],
        ['post', 'character.post', fn () => ['character_ids' => [test()->secondary_character->character_id]], 'unauthorized'],
        ['post', 'character.post', fn () => ['character_ids' => [test()->test_character->character_id]], 'ok'],
        ['post', 'character.post', fn () => ['character_ids' => [test()->test_character->character_id, test()->secondary_character->character_id]], 'unauthorized'],
        // GET
        ['get', 'character.character', fn () => test()->secondary_character->character_id, 'unauthorized'],
        ['get', 'character.character', fn () => test()->test_character->character_id, 'ok'],
        ['get', 'character.character_ids', fn () => ['character_ids' => [test()->secondary_character->character_id]], 'unauthorized'],
        ['get', 'character.character_ids', fn () => ['character_ids' => [test()->test_character->character_id]], 'ok'],
        ['get', 'character.character_ids', fn () => ['character_ids' => [test()->test_character->character_id, test()->secondary_character->character_id]], 'unauthorized'],
    ]);