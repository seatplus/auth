<?php

namespace Seatplus\Auth\DataTransferObjects;

use Illuminate\Support\Collection;
use Seatplus\Auth\Services\Dtos\AffiliationsDto;
use Spatie\DataTransferObject\DataTransferObject;

class CheckPermissionAffiliationDto extends DataTransferObject
{
    public Collection $requested_ids;
    public string $request_type;
    public AffiliationsDto $affiliationsDto;

    private Collection $validated_ids;

    public function __construct(...$args)
    {
        $this->validated_ids = collect();

        parent::__construct($args);
    }

    public function allIdsValidated() : bool
    {
        $different_ids = $this->requested_ids->diff($this->validated_ids);

        return $different_ids->isEmpty();
    }

    public function isCharacterRequestType() : bool
    {
        return $this->request_type === 'character';
    }

    public function isCorporationRequestType() : bool
    {
        return $this->request_type === 'corporation';
    }

    public function mergeValidatedIds(array|Collection $validatedIds)
    {
        $this->validated_ids = $this->validated_ids
            ->merge($validatedIds)
            ->unique();
    }
}