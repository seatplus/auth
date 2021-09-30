<?php

namespace Seatplus\Auth\Containers;

use Spatie\DataTransferObject\Attributes\Strict;

#[Strict]
class EveUser extends \Spatie\DataTransferObject\DataTransferObject
{

    public int $character_id;
    public string $character_owner_hash;

    // Token related
    public string $token;
    public string $refreshToken;
    public int $expiresIn;

    //jwt payload
    public array $user;

    public function getScopes(): array
    {
        return data_get($this->user, 'scp');
    }
}