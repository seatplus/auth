<?php

namespace Seatplus\Auth\Services;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Seatplus\Auth\Services\CharacterAffiliations\GetAffiliatedCharacterAffiliationsService;
use Seatplus\Auth\Services\CharacterAffiliations\GetOwnedCharacterAffiliationsService;
use Seatplus\Auth\Services\Dtos\AffiliationsDto;

class LimitAffiliatedService
{
    public function __construct(
        private AffiliationsDto $affiliationsDto,
        private Builder $query,
        private string $table,
        private string $column,
        private string $type = 'character'
    )
    {
    }

    public static function make(AffiliationsDto $affiliationsDto, Builder $query, string $table, string $column)
    {
        return new static($affiliationsDto, $query, $table, $column);
    }

    public function getQuery() : Builder
    {
        if($this->affiliationsDto->user->can('superuser')) {
            return $this->query->select($this->table . ".*");
        }

        $character_affiliations = $this->getOwnedCharacterAffiliations()
            ->union($this->getAffiliatedCharacterAffiliations());

        return $this->query->joinSub(
            $character_affiliations,
            'character_affiliations',
            sprintf("%s.%s", $this->table, $this->column),
            '=',
            sprintf("character_affiliations.%s_id", $this->type)
        )
            ->select($this->table . ".*");

    }

    private function getOwnedCharacterAffiliations() : Builder
    {
        return GetOwnedCharacterAffiliationsService::make($this->getAffiliationsDto())
            ->getQuery();
    }

    private function getAffiliatedCharacterAffiliations() : Builder
    {
        return GetAffiliatedCharacterAffiliationsService::make($this->getAffiliationsDto())
            ->getQuery();
    }

    /**
     * @return AffiliationsDto
     */
    public function getAffiliationsDto(): AffiliationsDto
    {
        return $this->affiliationsDto;
    }

    /**
     * @param string $type
     * @return LimitAffiliatedService
     */
    public function setType(string $type): LimitAffiliatedService
    {
        $this->type = $type;
        return $this;
    }


}