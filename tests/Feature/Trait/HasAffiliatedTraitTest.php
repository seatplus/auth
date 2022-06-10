<?php

use Seatplus\Auth\Models\Permissions\Affiliation;
use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Tests\Stubs\Contact;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

beforeEach(function () {


    test()->role = Role::create(['name' => faker()->name]);
    test()->permission = Permission::create(['name' => faker()->company]);

    test()->role->givePermissionTo(test()->permission);
    test()->role->activateMember(test()->test_user);

    \Illuminate\Support\Facades\Queue::fake();

    expect(Contact::all())->toHaveCount(0);

    test()->secondary_character = createContact()->contactable;
    test()->tertiary_character = createContact()->contactable;

    expect(Contact::all())->toHaveCount(2);
});

it('trows Unauthenticated exception if used without a session', function () {
    $contacts = Contact::query()
        ->isAffiliated('contactable_id')
        ->get();
})->throws('Unauthenticated');

it('returns owned contacts', function () {
    createContact(test()->test_character->character_id);

    expect(Contact::all())->toHaveCount(3);

    // do the same for test_user
    test()->actingAs(test()->test_user);

    $contacts = Contact::query()
        ->isAffiliated('contactable_id')
        ->get();

    expect($contacts)->toHaveCount(1);
});

it('return all contacts for superuser', function () {

    // test to only have 2 (2nd and 3rd character) contacts
    expect(Contact::all())->toHaveCount(2);

    test()->assignPermissionToTestUser('superuser');

    expect(test()->test_user->can('superuser'))->toBeTrue();

    test()->actingAs(test()->test_user);

    $contacts = Contact::query()
        ->isAffiliated('contactable_id')
        ->get();

    expect($contacts)->toHaveCount(2);
});

it('returns contacts of allowed and own character', function () {
    createContact(test()->test_character->character_id);

    // test to have test_character, 2nd and 3rd character contacts
    expect(Contact::all())->toHaveCount(3);

    test()->createAffiliation(
        test()->role,
        test()->secondary_character->character_id,
        CharacterInfo::class,
        'allowed'
    );

    test()->actingAs(test()->test_user);

    $contacts = Contact::query()
        ->isAffiliated('contactable_id', test()->permission->name)
        ->get();

    expect($contacts)->toHaveCount(2);
});

it('returns contacts of allowed entities', function (int $affiliatable_id, string $affiliatable_type) {

    // test to only have 2 (2nd and 3rd character) contacts
    expect(Contact::all())->toHaveCount(2);

    test()->createAffiliation(
        test()->role,
        $affiliatable_id,
        $affiliatable_type,
        'allowed'
    );

    test()->actingAs(test()->test_user);

    $contacts = Contact::query()
        ->isAffiliated('contactable_id', test()->permission->name)
        ->get();

    expect($contacts)->toHaveCount(1);
})->with([
    [fn () => test()->secondary_character->character_id, CharacterInfo::class],
    [fn () => test()->secondary_character->corporation->corporation_id, CorporationInfo::class],
    [fn () => test()->secondary_character->corporation->alliance_id, AllianceInfo::class],
]);

it('returns contacts of inverted entities', function (int $affiliatable_id, string $affiliatable_type) {

    // test to only have 2 (2nd and 3rd character) contacts
    expect(Contact::all())->toHaveCount(2);

    test()->createAffiliation(
        test()->role,
        $affiliatable_id,
        $affiliatable_type,
        'inverse'
    );

    test()->actingAs(test()->test_user);

    $contacts = Contact::query()
        ->isAffiliated('contactable_id', test()->permission->name)
        ->get();

    expect($contacts)->toHaveCount(1);
})->with([
    [fn () => test()->secondary_character->character_id, CharacterInfo::class],
    [fn () => test()->secondary_character->corporation->corporation_id, CorporationInfo::class],
    [fn () => test()->secondary_character->corporation->alliance_id, AllianceInfo::class],
]);

it('returns contacts of inverted entities and own', function (int $affiliatable_id, string $affiliatable_type) {
    createContact(test()->test_character->character_id);

    // test to have 3 contacts (test, 2nd and 3rd)
    expect(Contact::all())->toHaveCount(3);

    test()->createAffiliation(
        test()->role,
        $affiliatable_id,
        $affiliatable_type,
        'inverse'
    );

    test()->actingAs(test()->test_user);

    $contacts = Contact::query()
        ->isAffiliated('contactable_id', test()->permission->name)
        ->get();

    expect($contacts)->toHaveCount(3);
})->with([
    [fn () => test()->test_character->character_id, CharacterInfo::class],
    [fn () => test()->test_character->corporation->corporation_id, CorporationInfo::class],
    [fn () => test()->test_character->corporation->alliance_id, AllianceInfo::class],
]);

it('does not return contacts of forbidden entities', function (int $secondary_id, string $secondary_type, int $tertiary_id, string $tertiary_type) {


    // test to only have 2 (2nd and 3rd character) contacts
    expect(Contact::all())->toHaveCount(2);

    // invert secondary, now test user can see tert
    test()->createAffiliation(
        test()->role,
        $secondary_id,
        $secondary_type,
        'inverse'
    );

    // forbid tert
    test()->createAffiliation(
        test()->role,
        $tertiary_id,
        $tertiary_type,
        'forbidden'
    );

    expect(Affiliation::all())->toHaveCount(2);

    test()->actingAs(test()->test_user);

    $contacts = Contact::query()
        ->isAffiliated('contactable_id', test()->permission->name)
        ->get();

    expect($contacts)->toHaveCount(0);
})->with([
    [fn () => test()->secondary_character->character_id, CharacterInfo::class],
    [fn () => test()->secondary_character->corporation->corporation_id, CorporationInfo::class],
    [fn () => test()->secondary_character->corporation->alliance_id, AllianceInfo::class],
])->with([
    [fn () => test()->tertiary_character->character_id, CharacterInfo::class],
    [fn () => test()->tertiary_character->corporation->corporation_id, CorporationInfo::class],
    [fn () => test()->tertiary_character->corporation->alliance_id, AllianceInfo::class],
]);

it('returns own character id even if it is forbidden', function () {
    createContact(test()->test_character->character_id);

    expect(Contact::all())->toHaveCount(3);

    test()->createAffiliation(
        test()->role,
        test()->test_character->character_id,
        CharacterInfo::class,
        'forbidden'
    );

    test()->actingAs(test()->test_user);

    $contacts = Contact::query()
        ->isAffiliated('contactable_id', test()->permission->name)
        ->get();

    expect($contacts)->toHaveCount(1);
});

it('return corporation owned contacts for ($character_role, $corporation_role) ', function (string $character_role, string $corporation_role, bool $can_find_asset) {

    // give test_character corporation role
    \Seatplus\Eveapi\Models\Character\CharacterRole::factory()->create([
        'character_id' => test()->test_character->character_id,
        'roles' => [$character_role],
    ]);

    //dump(\Seatplus\Eveapi\Models\Character\CharacterRole::where('character_id', test()->test_character->character_id)->get());

    // create corporation asset
    createContact(test()->test_character->corporation->corporation_id);

    // query asset
    test()->actingAs(test()->test_user);

    $contacts = Contact::query()
        ->isAffiliated('contactable_id', test()->permission->name, $corporation_role)
        ->get();

    expect($contacts)->toHaveCount($can_find_asset ? 1 : 0);
})->with([
    ['Director', 'Director', true],
    ['Director', 'NoDirector', true],
    ['NoDirector', 'Director', false],
]);

it('supports array of corporation roles ', function (string $character_role, string|array $corporation_role, bool $can_find_asset) {

    // give test_character corporation role
    \Seatplus\Eveapi\Models\Character\CharacterRole::factory()->create([
        'character_id' => test()->test_character->character_id,
        'roles' => [$character_role],
    ]);

    //dump(\Seatplus\Eveapi\Models\Character\CharacterRole::where('character_id', test()->test_character->character_id)->get());

    // create corporation asset
    createContact(test()->test_character->corporation->corporation_id);

    // query asset
    test()->actingAs(test()->test_user);

    $contacts = Contact::query()
        ->isAffiliated('contactable_id', test()->permission->name, $corporation_role)
        ->get();

    expect($contacts)->toHaveCount($can_find_asset ? 1 : 0);
})->with([
    ['Director', ['Director', 'NoDirector'], true],
    ['Director', ['NoDirector'], true],
    ['NoDirector', ['Director', 'derp'], false],
]);

function createContact(int $character_id = null) : \Seatplus\Eveapi\Models\Contacts\Contact
{

    return \Seatplus\Eveapi\Models\Contacts\Contact::factory()->create([
        'contactable_id' => $character_id ?? CharacterInfo::factory(),
        'contactable_type' => CharacterInfo::class,
    ]);
}
