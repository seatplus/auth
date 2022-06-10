<?php

namespace Seatplus\Auth\Pipelines\Middleware;

use Seatplus\Auth\DataTransferObjects\CheckPermissionAffiliationDto;
use Seatplus\Auth\Services\Affiliations\GetOwnedAffiliatedIdsService;

class CheckOwnedAffiliatedIdsPipe extends CheckPermissionAffiliationPipeline
{

    protected function check(CheckPermissionAffiliationDto $checkPermissionAffiliationDto): CheckPermissionAffiliationDto
    {

        $validated_ids = GetOwnedAffiliatedIdsService::make($checkPermissionAffiliationDto->affiliationsDto)
            ->getQuery()
            ->pluck('affiliated_id')
            ->intersect($checkPermissionAffiliationDto->requested_ids);

        $checkPermissionAffiliationDto->mergeValidatedIds($validated_ids);

        return $checkPermissionAffiliationDto;
    }

    protected function shouldBeChecked(CheckPermissionAffiliationDto $checkPermissionAffiliationDto): bool
    {
        if($checkPermissionAffiliationDto->allIdsValidated()) {
            return false;
        }

        if($checkPermissionAffiliationDto->isCharacterRequestType()) {
            return false;
        }

        if($checkPermissionAffiliationDto->requested_ids->count() > 1) {
            return false;
        }

        return true;
    }
}