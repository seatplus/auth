<?php

use Seatplus\Auth\Services\Permissions\DTO\ValidateIdsDTO;

it('throws when more than one id parameter is present', function () {
    $dto = new ValidateIdsDTO(character_id: 1, corporation_id: 2);

    expect(fn () => $dto->get())->toThrow(InvalidArgumentException::class);
});

it('throws when an id parameter fails integer validation', function () {
    $dto = new ValidateIdsDTO(character_ids: ['not-an-integer']);

    expect(fn () => $dto->get())->toThrow(InvalidArgumentException::class);
});
