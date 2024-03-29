<?php

use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Services\Affiliations\GetAffiliatedIdsService;
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

    // delete all other character_affiliations
    CharacterAffiliation::query()
        ->whereNotIn('character_id', [
            test()->test_character->character_id,
            test()->secondary_character->character_id,
            test()->tertiary_character->character_id,
        ])->delete();

    // {character_id: 1, corporation_id: A, alliance_id: B}
    // {character_id: 2, corporation_id: A, alliance_id: B}
    // {character_id: 3, corporation_id: C, alliance_id: B}
    expect(test()->tertiary_character->corporation->corporation_id)
        ->not()->toBe(test()->secondary_character->corporation->corporation_id)
        ->and(test()->tertiary_character->alliance->alliance_id)
        ->toBe(test()->test_character->alliance->alliance_id);
});

it('returns inverse affiliated_ids via GetAffiliatedIdsService', function () {
    test()->createAffiliation(
        test()->role,
        test()->secondary_character->character_id,
        CharacterInfo::class,
        'inverse'
    );

    $affiliated_ids = GetAffiliatedIdsService::make(test()->affiliationsDto)
        ->getQuery()
        ->pluck('affiliated_id')
    ;

    // {character_id: 1, corporation_id: A, alliance_id: B}
    // {character_id: 2, corporation_id: A, alliance_id: B}
    // {character_id: 3, corporation_id: C, alliance_id: B}
    // result: [1,3]
    expect($affiliated_ids)
        ->toHaveCount(2)
        ->toBeCollection()
        ->contains(test()->test_character->character_id)->toBeTrue()
        ->contains(test()->secondary_character->character_id)->toBeFalse()
        ->contains(test()->tertiary_character->character_id)->toBeTrue();
});

it('returns allowed ids from affiliated corporation but not the forbidden character_id', function () {
    test()->createAffiliation(
        test()->role,
        test()->secondary_character->corporation->corporation_id,
        CorporationInfo::class,
        'allowed'
    );

    test()->createAffiliation(
        test()->role,
        test()->secondary_character->character_id,
        CharacterInfo::class,
        'forbidden'
    );

    $allowed_ids = GetAffiliatedIdsService::make(test()->affiliationsDto)
        ->getQuery()
        ->pluck('affiliated_id')
    ;

    // {character_id: 1, corporation_id: A, alliance_id: B}
    // {character_id: 2, corporation_id: A, alliance_id: B}
    // {character_id: 3, corporation_id: C, alliance_id: B}
    // result: [1,A]
    expect($allowed_ids)
        ->toHaveCount(2)
        ->toBeCollection()
        ->contains(test()->test_character->character_id)->toBeTrue()
        ->contains(test()->secondary_character->character_id)->toBeFalse()
        ->contains(test()->secondary_character->corporation->corporation_id)->toBeTrue()
        ->contains(test()->tertiary_character->character_id)->toBeFalse()
        ->contains(test()->tertiary_character->corporation->corporation_id)->toBeFalse()
    ;
});
