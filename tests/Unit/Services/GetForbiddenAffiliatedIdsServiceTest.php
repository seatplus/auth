<?php

use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Services\Affiliations\GetForbiddenAffiliatedIdService;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

beforeEach(function () {
    test()->role = Role::create(['name' => faker()->name]);
    test()->permission = Permission::create(['name' => faker()->company]);

    test()->role->givePermissionTo(test()->permission);
    test()->role->activateMember(test()->test_user);

    test()->affiliationsDto = new \Seatplus\Auth\Services\Dtos\AffiliationsDto(
        user: test()->test_user,
        permissions: [test()->permission->name]
    );

    \Illuminate\Support\Facades\Queue::fake();

    expect(test()->test_character->corporation->alliance_id)->not()->toBeNull();
    // {character_id: 1, corporation_id: A, alliance_id: B}

    test()->secondary_character = CharacterInfo::factory()->create();

    CharacterAffiliation::query()
        ->updateOrCreate([
            'character_id' => test()->secondary_character->character_id,
        ], [
            'corporation_id' => test()->test_character->corporation->corporation_id,
            'alliance_id' => test()->test_character->corporation->alliance_id,
        ]);

    // {character_id: 2, corporation_id: A, alliance_id: B}

    test()->tertiary_character = CharacterInfo::factory()->create();

    CharacterAffiliation::query()
        ->updateOrCreate([
            'character_id' => test()->tertiary_character->character_id,
        ], [
            'alliance_id' => test()->test_character->corporation->alliance_id,
        ]);

    // {character_id: 3, corporation_id: C, alliance_id: B}

    // {character_id: 1, corporation_id: A, alliance_id: B}
    // {character_id: 2, corporation_id: A, alliance_id: B}
    // {character_id: 3, corporation_id: C, alliance_id: B}
    expect(test()->tertiary_character->corporation->corporation_id)
        ->not()->toBe(test()->secondary_character->corporation->corporation_id)
        ->and(test()->tertiary_character->alliance->alliance_id)
        ->toBe(test()->test_character->alliance->alliance_id);
});

it('returns forbidden ids from forbidden character', function () {
    test()->createAffiliation(
        test()->role,
        test()->secondary_character->character_id,
        CharacterInfo::class,
        'forbidden'
    );

    $forbidden_ids = GetForbiddenAffiliatedIdService::make(test()->affiliationsDto)
        ->getQuery()
        ->pluck('forbidden_id')
    ;

    // {character_id: 1, corporation_id: A, alliance_id: B}
    // {character_id: 2, corporation_id: A, alliance_id: B}
    // {character_id: 3, corporation_id: C, alliance_id: B}
    // result: [2]
    expect($forbidden_ids)
        ->toHaveCount(1)
        ->toBeCollection()
        ->contains(test()->secondary_character->character_id)->toBeTrue();
});

it('returns forbidden ids from forbidden corporation but not owned character_id', function () {
    test()->createAffiliation(
        test()->role,
        test()->secondary_character->corporation->corporation_id,
        CorporationInfo::class,
        'forbidden'
    );

    $forbidden_ids = GetForbiddenAffiliatedIdService::make(test()->affiliationsDto)
        ->getQuery()
        ->pluck('forbidden_id')
    ;

    // {character_id: 1, corporation_id: A, alliance_id: B}
    // {character_id: 2, corporation_id: A, alliance_id: B}
    // {character_id: 3, corporation_id: C, alliance_id: B}
    // result: [2, A]
    expect($forbidden_ids)
        ->toHaveCount(2)
        ->toBeCollection()
        ->contains(test()->test_character->character_id)->toBeFalse()
        ->contains(test()->secondary_character->character_id)->toBeTrue()
        ->contains(test()->secondary_character->corporation->corporation_id)->toBeTrue()
        ->contains(test()->tertiary_character->character_id)->toBeFalse()
    ;
});

// TODO own corporation
it('returns forbidden ids from forbidden alliance', function () {
    test()->createAffiliation(
        test()->role,
        test()->secondary_character->corporation->alliance_id,
        AllianceInfo::class,
        'forbidden'
    );


    $forbidden_ids = GetForbiddenAffiliatedIdService::make(test()->affiliationsDto)
        ->getQuery()
        ->pluck('forbidden_id')
    ;

    // {character_id: 1, corporation_id: A, alliance_id: B}
    // {character_id: 2, corporation_id: A, alliance_id: B}
    // {character_id: 3, corporation_id: C, alliance_id: B}
    // result: [A,B,2,3,C]
    expect($forbidden_ids)
        ->toBeCollection()
        ->toHaveCount(5)
        ->contains(test()->test_character->character_id)->toBeFalse()
        ->contains(test()->secondary_character->character_id)->toBeTrue()
        ->contains(test()->tertiary_character->character_id)->toBeTrue()
        ->contains(test()->test_character->corporation->corporation_id)->toBeTrue()
        ->contains(test()->secondary_character->corporation->corporation_id)->toBeTrue()
        ->contains(test()->tertiary_character->corporation->corporation_id)->toBeTrue()
        ->contains(test()->secondary_character->corporation->alliance_id)->toBeTrue()
    ;
});

it('returns forbidden ids from forbidden alliance but not owned corporation via role', function () {
    test()->createAffiliation(
        test()->role,
        test()->secondary_character->corporation->alliance_id,
        AllianceInfo::class,
        'forbidden'
    );

    test()->affiliationsDto->corporation_roles = ['Director'];

    \Seatplus\Eveapi\Models\Character\CharacterRole::factory()->create([
        'character_id' => test()->test_character->character_id,
        'roles' => test()->affiliationsDto->corporation_roles,
    ]);


    $forbidden_ids = GetForbiddenAffiliatedIdService::make(test()->affiliationsDto)
        ->getQuery()
        ->pluck('forbidden_id')
    ;

    // {character_id: 1, corporation_id: A, alliance_id: B}
    // {character_id: 2, corporation_id: A, alliance_id: B}
    // {character_id: 3, corporation_id: C, alliance_id: B}
    // result: [B,2,3,C]
    expect($forbidden_ids)
        ->toBeCollection()
        ->toHaveCount(4)
        ->contains(test()->test_character->character_id)->toBeFalse()
        ->contains(test()->secondary_character->character_id)->toBeTrue()
        ->contains(test()->tertiary_character->character_id)->toBeTrue()
        ->contains(test()->test_character->corporation->corporation_id)->toBeFalse()
        ->contains(test()->secondary_character->corporation->corporation_id)->toBeFalse()
        ->contains(test()->tertiary_character->corporation->corporation_id)->toBeTrue()
        ->contains(test()->secondary_character->corporation->alliance_id)->toBeTrue()
    ;
});
