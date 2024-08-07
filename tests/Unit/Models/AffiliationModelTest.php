<?php

use Seatplus\Auth\Enums\AffiliationType;
use Seatplus\Auth\Models\Permissions\Affiliation;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

beforeEach(function () {
    Event::fake();

    test()->role = Role::create(['name' => 'derp']);
});

dataset('primary entities', [
    'character' => fn() => [test()->test_character->character_id, CharacterInfo::class],
    'corporation' => fn() => [test()->test_character->corporation_id, CorporationInfo::class],
    'alliance' => fn() => [test()->test_character->alliance_id, AllianceInfo::class],
]);

dataset('affiliation types', [
    'allowed' => AffiliationType::ALLOWED->value,
    'inverted' => AffiliationType::INVERSE->value,
    'forbidden' => AffiliationType::FORBIDDEN->value,
]);

it('has affiliated_ids attribute', function ($primary_entities, $affiliation_type) {

    [$entity_id, $entity_type] = $primary_entities();
    // Arrange
    $affiliation = Affiliation::query()->create([
        'role_id' => test()->role->id,
        'affiliatable_id' => $entity_id,
        'affiliatable_type' => $entity_type,
        'type' => $affiliation_type,
    ]);

    // Assert
    expect($affiliation->affiliated_ids)->toContain(test()->test_character->character_id)
        ->when($entity_type === CorporationInfo::class, function ($collection) {
            $collection->toContain(test()->test_character->corporation_id);
        })
        ->when($entity_type === AllianceInfo::class, function ($collection) {
            $collection->toContain(test()->test_character->alliance_id);
        });

})->with('primary entities')->with('affiliation types');

describe('relationship tests', function () {

    beforeEach(function () {
        Affiliation::query()->create([
            'role_id' => test()->role->id,
            'affiliatable_id' => test()->test_character->character_id,
            'affiliatable_type' => CharacterInfo::class,
            'type' => AffiliationType::ALLOWED->value,
        ]);
    });

    it('has role relationship', function () {
        // Arrange
        $affiliation = Affiliation::first();

        // Assert
        expect($affiliation->role)->toBeInstanceOf(Role::class);
    });

    it('has affiliatable relationship', function () {
        // Arrange
        $affiliation = Affiliation::first();

        // Assert
        expect($affiliation->affiliatable)->toBeInstanceOf(CharacterInfo::class);
    });
});


