<?php

use Seatplus\Auth\Enums\AffiliationType;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Services\Roles\BaseRoleService;
use Seatplus\Auth\Services\Roles\RoleAffiliatedIdsService;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

beforeEach(function () {

    \Illuminate\Support\Facades\Event::fake();

    test()->secondary_character = CharacterInfo::factory()->create();

    test()->tertiary_character = CharacterInfo::factory()->create();

    test()->role = Role::create(['name' => 'derp']);
});

dataset('entity_types', [
    CharacterInfo::class,
    CorporationInfo::class,
    AllianceInfo::class,
]);

function getId(string $entity_type, int $character_level)
{
    $character = match($character_level) {
        1 => test()->test_character,
        2 => test()->secondary_character,
        3 => test()->tertiary_character,
    };

    return match ($entity_type) {
        CharacterInfo::class => $character->character_id,
        CorporationInfo::class => $character->corporation_id,
        AllianceInfo::class => $character->alliance_id,
    };
}

describe('allowed only', function (){
    test('primary and secondary are affiliated ', function ($entity_type, $affiliation_type) {

        $primaray_id = getId($entity_type, 1);

        $secondary_id = getId($entity_type, 2);

        BaseRoleService::make(test()->role
        )->syncAffiliateManyEntities([
            [$primaray_id, $entity_type, $affiliation_type],
            [$secondary_id, $entity_type, $affiliation_type],
        ]);

        $affiliated_ids = (new RoleAffiliatedIdsService)->get(test()->role);
        $affiliated_ids = (new RoleAffiliatedIdsService)->get(test()->role);

        expect($affiliated_ids)->toContain($primaray_id)
            ->toContain($secondary_id)
            ->not()->toContain(test()->tertiary_character->character_id);

    })->with('entity_types')->with([AffiliationType::ALLOWED->value]);
});


describe('inverse only',function () {
    test('primary and secondary are affiliated, but not tertiary ', function ($entity_type, $affiliation_type) {

        $primary_id = getId($entity_type, 1);
        $secondary_id = getId($entity_type, 2);
        $tertiary_id = getId($entity_type, 3);

        BaseRoleService::make(test()->role
        )->syncAffiliateManyEntities([
            [$tertiary_id, $entity_type, $affiliation_type],
        ]);

        $affiliated_ids = (new RoleAffiliatedIdsService)->get(test()->role);

        expect($affiliated_ids)
            ->toContain($primary_id)
            ->toContain($secondary_id)
            ->not()->toContain(test()->tertiary_character->character_id)
            ->not()->toContain($tertiary_id);

    })->with('entity_types')->with([AffiliationType::INVERSE->value]);
});

describe('forbidden only',function () {
    test('primary and secondary are affiliated, but not tertiary ', function ($entity_type, $affiliation_type) {

        $primary_id = getId($entity_type, 1);
        $secondary_id = getId($entity_type, 2);
        $tertiary_id = getId($entity_type, 3);

        BaseRoleService::make(test()->role
        )->syncAffiliateManyEntities([
            [$tertiary_id, $entity_type, $affiliation_type],
        ]);

        $affiliated_ids = (new RoleAffiliatedIdsService)->get(test()->role);

        expect($affiliated_ids)
            ->not()->toContain($primary_id)
            ->not()->toContain($secondary_id)
            ->not()->toContain(test()->tertiary_character->character_id)
            ->not()->toContain($tertiary_id);

    })->with('entity_types')->with([AffiliationType::FORBIDDEN->value]);
});

describe('allowed and inverse',function () {
    test('testcharacter, secondary and tertiary are affiliated, but not tertiary ', function ($entity_type) {

        $primary_id = getId($entity_type, 1);
        $secondary_id = getId($entity_type, 2);
        $tertiary_id = getId($entity_type, 3);

        BaseRoleService::make(test()->role
        )->syncAffiliateManyEntities([
            [test()->test_character->character_id, CharacterInfo::class, AffiliationType::ALLOWED->value],
            [$primary_id, $entity_type, AffiliationType::INVERSE->value],
        ]);

        $affiliated_ids = (new RoleAffiliatedIdsService)->get(test()->role);

        expect($affiliated_ids)
            ->toContain(test()->test_character->character_id)
            ->toContain($secondary_id)
            ->toContain($tertiary_id)
            ->toContain(test()->secondary_character->character_id)
            ->toContain(test()->tertiary_character->character_id);

    })->with('entity_types');
});

describe('allowed and forbidden',function () {
    test('primary affiliated but test_character forbidden ', function ($entity_type) {

        $primary_id = getId($entity_type, 1);

        BaseRoleService::make(test()->role
        )->syncAffiliateManyEntities([
            [test()->test_character->character_id, CharacterInfo::class, AffiliationType::FORBIDDEN->value],
            [$primary_id, $entity_type, AffiliationType::ALLOWED->value],
        ]);

        $affiliated_ids = (new RoleAffiliatedIdsService)->get(test()->role);

        expect($affiliated_ids)
            ->not()->toContain(test()->test_character->character_id)
            ->when($entity_type === CharacterInfo::class, function ($collection)  {
                $collection->toHaveCount(0);
            })
            ->when($entity_type !== CharacterInfo::class, function ($collection) use ($primary_id) {
                $collection->toContain($primary_id);
            });

    })->with('entity_types');
});


describe('inverse and forbidden',function () {
    test('test_character forbidden but primary affiliated through inverse', function ($entity_type) {

        $primary_id = getId($entity_type, 1);
        $secondary_id = getId($entity_type, 2);

        BaseRoleService::make(test()->role
        )->syncAffiliateManyEntities([
            [test()->test_character->character_id, CharacterInfo::class, AffiliationType::FORBIDDEN->value],
            [$secondary_id, $entity_type, AffiliationType::INVERSE->value],
        ]);

        $affiliated_ids = (new RoleAffiliatedIdsService)->get(test()->role);

        expect($affiliated_ids)
            ->not()->toContain(test()->test_character->character_id)
            ->when($entity_type !== CharacterInfo::class, function ($collection) use ($primary_id) {
                $collection->toContain($primary_id);
            });

    })->with('entity_types');
});

describe('allowed, inverse and forbidden',function () {
    test('test_character forbidden, primary allowed, secondary inverse', function ($entity_type) {

        $primary_id = getId($entity_type, 1);
        $secondary_id = getId($entity_type, 2);

        BaseRoleService::make(test()->role
        )->syncAffiliateManyEntities([
            [test()->test_character->character_id, CharacterInfo::class, AffiliationType::FORBIDDEN->value],
            [$primary_id, $entity_type, AffiliationType::ALLOWED->value],
            [$secondary_id, $entity_type, AffiliationType::INVERSE->value],
        ]);

        $affiliated_ids = (new RoleAffiliatedIdsService)->get(test()->role);

        expect($affiliated_ids)
            ->toContain(test()->tertiary_character->character_id)
            ->not()->toContain(test()->test_character->character_id)
            ->not()->toContain($secondary_id);

    })->with('entity_types');

});
