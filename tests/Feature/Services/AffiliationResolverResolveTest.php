<?php

use Illuminate\Support\Facades\Event;
use Seatplus\Auth\Enums\AffiliationType;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Services\Roles\AffiliationResolver;
use Seatplus\Auth\Services\Roles\AutomaticRoleService;
use Seatplus\Auth\Services\Roles\DTO\AffiliationData;
use Seatplus\Eveapi\Models\Character\CharacterInfo;

beforeEach(function () {

    Event::fake();

    test()->secondary_character = CharacterInfo::factory()->create();

    test()->tertiary_character = CharacterInfo::factory()->create();

    test()->role = Role::create(['name' => 'derp']);

    $this->service = new AutomaticRoleService($this->role);
});

dataset('entity_types', [
    'character',
    'corporation',
    'alliance',
]);

function getId(string $entity_type, int $character_level)
{
    $character = match ($character_level) {
        1 => test()->test_character,
        2 => test()->secondary_character,
        3 => test()->tertiary_character,
    };

    return match ($entity_type) {
        'character' => $character->character_id,
        'corporation' => $character->corporation_id,
        'alliance' => $character->alliance_id,
    };
}

describe('allowed only', function () {
    test('primary and secondary are affiliated ', function ($entity_type, $affiliation_type) {

        $primaray_id = getId($entity_type, 1);

        $secondary_id = getId($entity_type, 2);

        $this->service->syncAffiliateManyEntities(
            new AffiliationData($primaray_id, $entity_type, AffiliationType::from($affiliation_type)),
            new AffiliationData($secondary_id, $entity_type, AffiliationType::from($affiliation_type)),
        );

        $affiliated_ids = (new AffiliationResolver)->resolve([test()->role->id]);

        expect($affiliated_ids)->toContain($primaray_id)
            ->toContain($secondary_id)
            ->not()->toContain(test()->tertiary_character->character_id);

    })->with('entity_types')->with([AffiliationType::ALLOWED->value]);
});

describe('inverse only', function () {
    test('primary and secondary are affiliated, but not tertiary ', function ($entity_type, $affiliation_type) {

        $primary_id = getId($entity_type, 1);
        $secondary_id = getId($entity_type, 2);
        $tertiary_id = getId($entity_type, 3);

        $this->service->syncAffiliateManyEntities(
            new AffiliationData($tertiary_id, $entity_type, AffiliationType::from($affiliation_type)),
        );

        $affiliated_ids = (new AffiliationResolver)->resolve([test()->role->id]);

        expect($affiliated_ids)
            ->toContain($primary_id)
            ->toContain($secondary_id)
            ->not()->toContain(test()->tertiary_character->character_id)
            ->not()->toContain($tertiary_id);

    })->with('entity_types')->with([AffiliationType::INVERSE->value]);
});

describe('forbidden only', function () {
    test('primary and secondary are affiliated, but not tertiary ', function ($entity_type, $affiliation_type) {

        $primary_id = getId($entity_type, 1);
        $secondary_id = getId($entity_type, 2);
        $tertiary_id = getId($entity_type, 3);

        $this->service->syncAffiliateManyEntities(
            new AffiliationData($tertiary_id, $entity_type, AffiliationType::from($affiliation_type)),
        );

        $affiliated_ids = (new AffiliationResolver)->resolve([test()->role->id]);

        expect($affiliated_ids)
            ->not()->toContain($primary_id)
            ->not()->toContain($secondary_id)
            ->not()->toContain(test()->tertiary_character->character_id)
            ->not()->toContain($tertiary_id);

    })->with('entity_types')->with([AffiliationType::FORBIDDEN->value]);
});

describe('allowed and inverse', function () {
    test('testcharacter, secondary and tertiary are affiliated, but not tertiary ', function ($entity_type) {

        $primary_id = getId($entity_type, 1);
        $secondary_id = getId($entity_type, 2);
        $tertiary_id = getId($entity_type, 3);

        $this->service->syncAffiliateManyEntities(
            new AffiliationData(test()->test_character->character_id, 'character', AffiliationType::ALLOWED),
            new AffiliationData($primary_id, $entity_type, AffiliationType::INVERSE),
        );

        $affiliated_ids = (new AffiliationResolver)->resolve([test()->role->id]);

        expect($affiliated_ids)
            ->toContain(test()->test_character->character_id)
            ->toContain($secondary_id)
            ->toContain($tertiary_id)
            ->toContain(test()->secondary_character->character_id)
            ->toContain(test()->tertiary_character->character_id);

    })->with('entity_types');
});

describe('allowed and forbidden', function () {
    test('primary affiliated but test_character forbidden ', function ($entity_type) {

        $primary_id = getId($entity_type, 1);

        $this->service->syncAffiliateManyEntities(
            new AffiliationData(test()->test_character->character_id, 'character', AffiliationType::FORBIDDEN),
            new AffiliationData($primary_id, $entity_type, AffiliationType::ALLOWED),
        );

        $affiliated_ids = (new AffiliationResolver)->resolve([test()->role->id]);

        expect($affiliated_ids)
            ->not()->toContain(test()->test_character->character_id)
            ->when($entity_type === 'character', function ($collection) {
                $collection->toHaveCount(0);
            })
            ->when($entity_type !== 'character', function ($collection) use ($primary_id) {
                $collection->toContain($primary_id);
            });

    })->with('entity_types');
});

describe('inverse and forbidden', function () {
    test('test_character forbidden but primary affiliated through inverse', function ($entity_type) {

        $primary_id = getId($entity_type, 1);
        $secondary_id = getId($entity_type, 2);

        $this->service->syncAffiliateManyEntities(
            new AffiliationData(test()->test_character->character_id, 'character', AffiliationType::FORBIDDEN),
            new AffiliationData($secondary_id, $entity_type, AffiliationType::INVERSE),
        );

        $affiliated_ids = (new AffiliationResolver)->resolve([test()->role->id]);

        expect($affiliated_ids)
            ->not()->toContain(test()->test_character->character_id)
            ->when($entity_type !== 'character', function ($collection) use ($primary_id) {
                $collection->toContain($primary_id);
            });

    })->with('entity_types');
});

describe('allowed, inverse and forbidden', function () {
    test('test_character forbidden, primary allowed, secondary inverse', function ($entity_type) {

        $primary_id = getId($entity_type, 1);
        $secondary_id = getId($entity_type, 2);

        $this->service->syncAffiliateManyEntities(
            new AffiliationData(test()->test_character->character_id, 'character', AffiliationType::FORBIDDEN),
            new AffiliationData($primary_id, $entity_type, AffiliationType::ALLOWED),
            new AffiliationData($secondary_id, $entity_type, AffiliationType::INVERSE),
        );

        $affiliated_ids = (new AffiliationResolver)->resolve([test()->role->id]);

        expect($affiliated_ids)
            ->toContain(test()->tertiary_character->character_id)
            ->not()->toContain(test()->test_character->character_id)
            ->not()->toContain($secondary_id);

    })->with('entity_types');

});
