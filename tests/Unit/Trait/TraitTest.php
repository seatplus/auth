<?php

use Seatplus\Auth\Models\Permissions\Affiliation;
use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Tests\Stubs\Assets;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

beforeEach(function () {
    test()->role = Role::create(['name' => faker()->name]);
    test()->permission = Permission::create(['name' => faker()->company]);

    test()->role->givePermissionTo(test()->permission);
    test()->role->activateMember(test()->test_user);

    \Illuminate\Support\Facades\Queue::fake();

    expect(Assets::all())->toHaveCount(0);

    test()->secondary_character = createAsset()->assetable;
    test()->tertiary_character = createAsset()->assetable;

    expect(Assets::all())->toHaveCount(2);
});

it('trows Unauthenticated exception if used without a session', function () {
    $assets = Assets::query()
        ->affiliatedCharacters('assetable_id')
        ->get();
})->throws('Unauthenticated');

it('returns owned assets', function () {
    createAsset(test()->test_character->character_id);

    expect(Assets::all())->toHaveCount(3);

    // do the same for test_user
    test()->actingAs(test()->test_user);

    $assets = Assets::query()
        ->affiliatedCharacters('assetable_id')
        ->get();

    expect($assets)->toHaveCount(1);
});

it('return all assets for superuser', function () {

    // test to only have 2 (2nd and 3rd character) assets
    expect(Assets::all())->toHaveCount(2);

    test()->assignPermissionToTestUser('superuser');

    expect(test()->test_user->can('superuser'))->toBeTrue();

    test()->actingAs(test()->test_user);

    $assets = Assets::query()
        ->affiliatedCharacters('assetable_id')
        ->get();

    expect($assets)->toHaveCount(2);
});

it('returns assets of allowed and own character', function () {
    createAsset(test()->test_character->character_id);

    // test to have test_character, 2nd and 3rd character assets
    expect(Assets::all())->toHaveCount(3);

    test()->createAffiliation(
        test()->secondary_character->character_id,
        CharacterInfo::class,
        'allowed'
    );

    test()->actingAs(test()->test_user);

    $assets = Assets::query()
        ->affiliatedCharacters('assetable_id', test()->permission->name)
        ->get();

    expect($assets)->toHaveCount(2);
});

it('returns assets of allowed entities', function (int $affiliatable_id, string $affiliatable_type) {

    // test to only have 2 (2nd and 3rd character) assets
    expect(Assets::all())->toHaveCount(2);

    test()->createAffiliation(
        $affiliatable_id,
        $affiliatable_type,
        'allowed'
    );

    test()->actingAs(test()->test_user);

    $assets = Assets::query()
        ->affiliatedCharacters('assetable_id', test()->permission->name)
        ->get();

    expect($assets)->toHaveCount(1);
})->with([
    [fn () => test()->secondary_character->character_id, CharacterInfo::class],
    [fn () => test()->secondary_character->corporation->corporation_id, CorporationInfo::class],
    [fn () => test()->secondary_character->corporation->alliance_id, AllianceInfo::class],
]);

it('returns assets of inverted entities', function (int $affiliatable_id, string $affiliatable_type) {

    // test to only have 2 (2nd and 3rd character) assets
    expect(Assets::all())->toHaveCount(2);

    test()->createAffiliation(
        $affiliatable_id,
        $affiliatable_type,
        'inverse'
    );

    test()->actingAs(test()->test_user);

    $assets = Assets::query()
        ->affiliatedCharacters('assetable_id', test()->permission->name)
        ->get();

    expect($assets)->toHaveCount(1);
})->with([
    [fn () => test()->secondary_character->character_id, CharacterInfo::class],
    [fn () => test()->secondary_character->corporation->corporation_id, CorporationInfo::class],
    [fn () => test()->secondary_character->corporation->alliance_id, AllianceInfo::class],
]);

it('returns assets of inverted entities and own', function (int $affiliatable_id, string $affiliatable_type) {
    createAsset(test()->test_character->character_id);

    // test to have 3 assets (test, 2nd and 3rd)
    expect(Assets::all())->toHaveCount(3);

    test()->createAffiliation(
        $affiliatable_id,
        $affiliatable_type,
        'inverse'
    );

    test()->actingAs(test()->test_user);

    $assets = Assets::query()
        ->affiliatedCharacters('assetable_id', test()->permission->name)
        ->get();

    expect($assets)->toHaveCount(3);
})->with([
    [fn () => test()->test_character->character_id, CharacterInfo::class],
    [fn () => test()->test_character->corporation->corporation_id, CorporationInfo::class],
    [fn () => test()->test_character->corporation->alliance_id, AllianceInfo::class],
]);

it('does not return assets of forbidden entities', function (int $secondary_id, string $secondary_type, int $tertiary_id, string $tertiary_type) {


    // test to only have 2 (2nd and 3rd character) assets
    expect(Assets::all())->toHaveCount(2);

    // invert secondary, now test user can see tert
    test()->createAffiliation(
        $secondary_id,
        $secondary_type,
        'inverse'
    );

    // forbid tert
    test()->createAffiliation(
        $tertiary_id,
        $tertiary_type,
        'forbidden'
    );

    expect(Affiliation::all())->toHaveCount(2);

    test()->actingAs(test()->test_user);

    dump($tertiary_id);
    $assets = Assets::query()
        ->affiliatedCharacters('assetable_id', test()->permission->name)
        ->get();

    expect($assets)->toHaveCount(0);
})->with([
    [fn () => test()->secondary_character->character_id, CharacterInfo::class],
    [fn () => test()->secondary_character->corporation->corporation_id, CorporationInfo::class],
    [fn () => test()->secondary_character->corporation->alliance_id, AllianceInfo::class],
])->with([
    [fn () => test()->tertiary_character->character_id, CharacterInfo::class],
    [fn () => test()->tertiary_character->corporation->corporation_id, CorporationInfo::class],
    [fn () => test()->tertiary_character->corporation->alliance_id, AllianceInfo::class],
])->only();

function createAffiliation($affiliatable_id, $affiliatable_type, $type = 'allowed'): Affiliation
{
    return test()->role->affiliations()->create([
        'affiliatable_id' => $affiliatable_id,
        'affiliatable_type' => $affiliatable_type,
        'type' => $type,
    ]);
}

function createAsset(?int $character_id = null) : \Seatplus\Eveapi\Models\Assets\Asset
{
    return Assets::factory()->create([
        'assetable_id' => $character_id ?? CharacterInfo::factory(),
        'assetable_type' => CharacterInfo::class,
    ]);
}
