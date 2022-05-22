<?php

namespace Seatplus\Auth\Traits;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\CharacterAffiliations\GetAffiliatedCharacterAffiliationsService;
use Seatplus\Auth\Services\Dtos\AffiliationsDto;
use Seatplus\Auth\Services\LimitAffiliatedService;

trait HasAffiliated
{
    private User $user;
    private Builder $affiliation;
    private string $permission;
    private array $corporation_roles = [];
    private AffiliationsDto $affiliationsDto;

    /**
     * @return AffiliationsDto
     */
    public function getAffiliationsDto(): AffiliationsDto
    {
        return $this->affiliationsDto;
    }

    /**
     * @return string|null
     */
    public function getCorporationRoles(): array
    {
        return $this->corporation_roles;
    }

    public function scopeAffiliatedCharacters(Builder $query, string $column, null|string $permission = null)
    {
        $this->setPermission($permission);

        return $this->buildQuery('character', $query, $column);
    }

    public function scopeAffiliatedCorporations(Builder $query, string $column, null|string $permission = null, string|array $corporation_roles = []) : Builder
    {
        $this->setPermission($permission);
        $this->setCorporationRoles($corporation_roles);

        return $this->buildQuery('corporation', $query, $column);
    }

    private function setAffiliationsDto()
    {
        $this->affiliationsDto = new AffiliationsDto(
            user: $this->getUser(),
            permission: $this->getPermission(),
            corporation_roles: $this->getCorporationRoles()
        );
    }

    private function buildQuery(string $type, Builder $query, string $column) : Builder
    {
        throw_if(auth()->guest(), 'Unauthenticated');

        if ($this->getUser()->can('superuser')) {
            return $query;
        }

        $this->setAffiliationsDto();

        return LimitAffiliatedService::make(
            $this->getAffiliationsDto(),
            $query,
            $this->getTable(),
            $column
        )
            ->setType($type)
            ->getQuery();
    }

    public function setCorporationRoles(string|array $corporation_roles): void
    {
        $corporation_roles = is_array($corporation_roles) ? array_map('strval', $corporation_roles) : [$corporation_roles];

        $this->corporation_roles = $corporation_roles;
    }

    private function convertToPermissionString(string $permission) : string
    {
        if (class_exists($permission)) {
            return config('eveapi.permissions.' . $permission) ?? $permission;
        }

        return $permission;
    }

    private function getAffiliatedCharacterAffiliations() : Builder
    {
        return GetAffiliatedCharacterAffiliationsService::make($this->getAffiliationsDto())
            ->getQuery();
    }

    private function getUser(): User
    {
        if (! isset($this->user)) {
            $this->user = auth()->user();
        }

        return $this->user;
    }

    /**
     * @return string
     */
    public function getPermission(): string
    {
        return $this->permission;
    }

    /**
     * @param string|null $permission
     */
    public function setPermission(?string $permission): void
    {
        if (is_null($permission)) {
            $permission = get_class($this);
        }

        $permission = $this->convertToPermissionString($permission);

        $this->permission = $permission;
    }
}
