<?php

use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Services\Affiliations\GetOwnedAffiliatedIdsService;
use Seatplus\Eveapi\Models\Character\CharacterInfo;

beforeEach(function () {
    test()->role = Role::create(['name' => faker()->name]);
    test()->permission = Permission::create(['name' => faker()->company]);

    test()->role->givePermissionTo(test()->permission);

    test()->affiliationsDto = new \Seatplus\Auth\Services\Dtos\AffiliationsDto(
        user: test()->test_user,
        permissions: [test()->permission->name],
        corporation_roles: ['Director']
    );

    \Illuminate\Support\Facades\Queue::fake();

    expect(test()->test_character->corporation->alliance_id)->not()->toBeNull();
    // {character_id: 1, corporation_id: A, alliance_id: B}
});

it('returns own character ids', function () {
    test()->createAffiliation(
        test()->role,
        test()->test_character->character_id,
        CharacterInfo::class,
        'inverse'
    );

    $allowed_ids = GetOwnedAffiliatedIdsService::make(test()->affiliationsDto)
        ->getQuery()
        ->pluck('affiliated_id')
        ;

    // {character_id: 1, corporation_id: A, alliance_id: B}
    // result: [1]
    expect($allowed_ids)
        ->toHaveCount(1)
        ->toBeCollection()
        ->contains(test()->test_character->character_id)->toBeTrue()
       ;
});

it('returns owned character_id and corporation_id if corp role exists', function () {
    \Seatplus\Eveapi\Models\Character\CharacterRole::factory()->create([
        'character_id' => test()->test_character->character_id,
        'roles' => test()->affiliationsDto->corporation_roles,
    ]);

    $allowed_ids = GetOwnedAffiliatedIdsService::make(test()->affiliationsDto)
        ->getQuery()
        ->pluck('affiliated_id')
    ;

    // {character_id: 1, corporation_id: A, alliance_id: B}
    // result: [3, A]
    expect($allowed_ids)
        ->toHaveCount(2)
        ->toBeCollection()
        ->contains(test()->test_character->character_id)->toBeTrue()
        ->contains(test()->test_character->corporation->corporation_id)->toBeTrue()
    ;
});
