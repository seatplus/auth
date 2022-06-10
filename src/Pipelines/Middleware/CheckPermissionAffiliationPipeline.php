<?php

namespace Seatplus\Auth\Pipelines\Middleware;

use Closure;
use Seatplus\Auth\DataTransferObjects\CheckPermissionAffiliationDto;

abstract class CheckPermissionAffiliationPipeline implements CheckPermissionAffiliationPipelineInterface
{
    public function handle(CheckPermissionAffiliationDto $checkPermissionAffiliationDto, Closure $next) : CheckPermissionAffiliationDto
    {
        if(!$this->shouldBeChecked($checkPermissionAffiliationDto)) {
            return $next($checkPermissionAffiliationDto);
        }

        return $next($this->check($checkPermissionAffiliationDto));
    }

    abstract protected function check(CheckPermissionAffiliationDto $checkPermissionAffiliationDto) : CheckPermissionAffiliationDto;

    abstract protected function shouldBeChecked(CheckPermissionAffiliationDto $checkPermissionAffiliationDto) : bool;
}