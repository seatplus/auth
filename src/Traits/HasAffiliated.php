<?php

namespace Seatplus\Auth\Traits;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\Affiliations\GetAffiliatedIdsService;
use Seatplus\Auth\Services\Affiliations\GetOwnedAffiliatedIdsService;
use Seatplus\Auth\Services\Dtos\AffiliationsDto;

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
        if (! isset($this->affiliationsDto)) {
            $this->setAffiliationsDto();
        }

        return $this->affiliationsDto;
    }

    /**
     * @return string|null
     */
    public function getCorporationRoles(): array
    {
        return $this->corporation_roles;
    }

    public function scopeIsAffiliated(Builder $query, string $column, null|string $permission = null, string|array $corporation_roles = []) : Builder
    {
        $this->setPermission($permission);

        if ($corporation_roles) {
            $this->setCorporationRoles($corporation_roles);
        }

        return $this->buildQuery($query, $column);
    }

    private function setAffiliationsDto()
    {
        $this->affiliationsDto = new AffiliationsDto(
            user: $this->getUser(),
            permission: $this->getPermission(),
            corporation_roles: $this->getCorporationRoles()
        );
    }

    private function buildQuery(Builder $query, string $column) : Builder
    {
        throw_if(auth()->guest(), 'Unauthenticated');

        if ($this->getUser()->can('superuser')) {
            return $query;
        }

        $affiliated_ids_query = GetOwnedAffiliatedIdsService::make($this->getAffiliationsDto())->getQuery();
        $owned_ids_query = GetAffiliatedIdsService::make($this->getAffiliationsDto())->getQuery();

        return $query
            ->joinSub(
                $affiliated_ids_query->union($owned_ids_query),
                'affiliated',
                sprintf("%s.%s", $this->getTable(), $column),
                '=',
                'affiliated.affiliated_id'
            )
            ->select($this->getTable() . ".*");
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
