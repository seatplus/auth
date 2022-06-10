<?php

namespace Seatplus\Auth\Pipelines\Middleware;

use Closure;
use Seatplus\Auth\DataTransferObjects\CheckPermissionAffiliationDto;

interface CheckPermissionAffiliationPipelineInterface
{

    public function handle(CheckPermissionAffiliationDto $checkPermissionAffiliationDto, Closure $next) : CheckPermissionAffiliationDto;
}