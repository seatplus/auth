<?php

namespace Seatplus\Auth\Services\Roles;

interface RoleServiceInterface
{
    public function syncMembers(): void;

    public function handleMembers(): void;

    public function syncAffiliateManyEntities(array $entity_sets): void;
}
