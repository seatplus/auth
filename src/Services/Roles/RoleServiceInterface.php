<?php

declare(strict_types=1);

namespace Seatplus\Auth\Services\Roles;

use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\Roles\DTO\AffiliationData;

interface RoleServiceInterface
{
    public function syncMembers(): void;

    public function handleMembers(): void;

    public function syncAffiliateManyEntities(AffiliationData ...$entity_sets): void;

    public function canView(User $user): bool;

    public function canJoin(User $user): bool;

    public function canModerate(User $user): bool;
}
