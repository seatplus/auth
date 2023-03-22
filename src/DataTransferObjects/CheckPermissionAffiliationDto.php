<?php

namespace Seatplus\Auth\DataTransferObjects;

use Illuminate\Support\Collection;
use Seatplus\Auth\Services\Dtos\AffiliationsDto;

class CheckPermissionAffiliationDto
{
    public function __construct(
        public Collection $requested_ids,
        public AffiliationsDto $affiliationsDto,
        private ?Collection $validated_ids = null,
    ) {
        $this->validated_ids = collect();
    }

    public function allIdsValidated() : bool
    {
        $different_ids = $this->requested_ids->diff($this->validated_ids);

        return $different_ids->isEmpty();
    }

    public function mergeValidatedIds(array|Collection $validatedIds)
    {
        $this->validated_ids = $this->validated_ids
            ->merge($validatedIds)
            ->unique();
    }
}
