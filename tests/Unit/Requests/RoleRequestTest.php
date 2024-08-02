<?php

use Seatplus\Auth\Enums\AffiliationType;
use Seatplus\Auth\Http\Requests\RoleRequest;

function validate(array $data): bool
{
    $request = new RoleRequest();
    $validator = Validator::make($data, $request->rules());

    return $validator->passes();
}

dataset('role request', [
    fn() => [
        'role_id' => 1,
        'affiliated' => [
            [
                'entity_id' => 1,
                'entity_type' => 'corporation',
                'affiliation_type' => AffiliationType::cases()[fake()->randomElement([0,1,2])]->value
            ]
        ],
        'assigned' => [
            [
                'entity_id' => 1,
                'entity_type' => fake()->randomElement(['corporation', 'alliance']),
                'can_moderate' => fake()->boolean()
            ]
        ]
    ]
]);

it('can validate role request', function ($data) {

    expect(validate($data))->toBeTrue();
})->with('role request');

it('fails when role_id is missing', function ($data) {
    unset($data['role_id']);

    expect(validate($data))->toBeFalse();
})->with('role request');

it('does not fail when affiliated is missing', function ($data) {
    unset($data['affiliated']);

    expect(validate($data))->toBeTrue();
})->with('role request');

it('fails when affiliated.*.entity_id is missing', function ($data) {
    unset($data['affiliated'][0]['entity_id']);

    expect(validate($data))->toBeFalse();
})->with('role request');

it('fails when affiliation_type is not in ENUM', function ($data) {

    $data['affiliated'][0]['affiliation_type'] = 'not in enum';

    expect(validate($data))->toBeFalse();
})->with('role request');

it('fails when assigned.*.entity_type is not corporation or alliance', function ($data) {

    $data['assigned'][0]['entity_type'] = 'not corporation or alliance';

    expect(validate($data))->toBeFalse();
})->with('role request');

it('validates when assigned is missing', function ($data) {
    unset($data['assigned']);

    expect(validate($data))->toBeTrue();
})->with('role request');
