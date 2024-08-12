<?php

namespace Seatplus\Auth\Services\Roles;

use Seatplus\Auth\Models\User;

interface RoleServiceInterface
{
    public function syncMembers(): void;

    public function handleMembers(): void;

    public function syncAffiliateManyEntities(array $entity_sets): void;

    public function canView(User $user): bool;

    public function canJoin(User $user): bool;

    public function canModerate(User $user): bool;
}
