<?php

namespace Seatplus\Auth\Services\SsoScopes;

use Seatplus\Auth\Models\User;

class IsUserCompliantService
{
    private BuildScopesArrayService $build_scopes_array_service;

    public function __construct(
        private readonly bool $consider_applications = true
    )
    {
        $this->build_scopes_array_service = new BuildScopesArrayService($this->consider_applications);
    }

    public function check(User $user): bool
    {
        $missing_scopes = $this->getMissingScopes($user);

        return $this->isUserCompliant($missing_scopes);
    }

    public function getMissingScopes(User $user): array
    {
        $scopes = $this->build_scopes_array_service
            ->get($user);

        return collect($scopes)
            ->pluck('missing_scopes')
            ->toArray();
    }

    private function isUserCompliant(array $missing_scopes): bool
    {
        $flat_missing_scopes = collect($missing_scopes)
            ->flatten()
            ->unique();

        return $flat_missing_scopes->isEmpty();
    }

}
