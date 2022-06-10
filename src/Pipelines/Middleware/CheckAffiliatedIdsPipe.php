<?php

namespace Seatplus\Auth\Pipelines\Middleware;

use Seatplus\Auth\DataTransferObjects\CheckPermissionAffiliationDto;
use Seatplus\Auth\Services\Affiliations\GetAffiliatedIdsService;

class CheckAffiliatedIdsPipe extends CheckPermissionAffiliationPipeline
{
    protected function check(CheckPermissionAffiliationDto $checkPermissionAffiliationDto): CheckPermissionAffiliationDto
    {
        $validated_ids = GetAffiliatedIdsService::make($checkPermissionAffiliationDto->affiliationsDto)
            ->getQuery()
            ->pluck('affiliated_id')
            ->intersect($checkPermissionAffiliationDto->requested_ids);

        if ($validated_ids->isEmpty()) {
            $affiliated_ids = GetAffiliatedIdsService::make($checkPermissionAffiliationDto->affiliationsDto)
                ->getQuery()
                ->get();

            dd('affiliated: ', $affiliated_ids, 'requested: ', $checkPermissionAffiliationDto->requested_ids, $checkPermissionAffiliationDto->affiliationsDto);
        }

        $checkPermissionAffiliationDto->mergeValidatedIds($validated_ids);

        return $checkPermissionAffiliationDto;
    }

    protected function shouldBeChecked(CheckPermissionAffiliationDto $checkPermissionAffiliationDto): bool
    {
        return ! $checkPermissionAffiliationDto->allIdsValidated();
    }
}
