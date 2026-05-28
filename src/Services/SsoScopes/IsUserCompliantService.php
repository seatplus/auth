<?php

declare(strict_types=1);

namespace Seatplus\Auth\Services\SsoScopes;

use Seatplus\Auth\Models\User;

class IsUserCompliantService
{
    private readonly BuildScopesArrayService $buildScopesArrayService;

    public function __construct(
        private readonly bool $considerApplications = true
    ) {
        $this->buildScopesArrayService = new BuildScopesArrayService($this->considerApplications);
    }

    public function check(User $user): bool
    {
        $missing_scopes = $this->getMissingScopes($user);

        return $this->isUserCompliant($missing_scopes);
    }

    public function getMissingScopes(User $user): array
    {
        $scopes = $this->buildScopesArrayService
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
