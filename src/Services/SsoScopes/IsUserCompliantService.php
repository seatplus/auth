<?php

declare(strict_types=1);

namespace Seatplus\Auth\Services\SsoScopes;

use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Models\Character\CharacterInfo;

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
        return $this->getMissingCharacterScopes($user) === [];
    }

    /**
     * The characters that are missing required scopes, each with the scopes it is missing.
     *
     * Prefer this over getMissingScopes(): that one plucks the scope lists and so drops the character
     * they belong to, which leaves a caller unable to say *who* has to re-authorise — and it is the
     * value CheckRequiredScopes hands to redirectTo(), so a subclass rendering a per-character page had
     * nothing to render from.
     *
     * @return array<int, array{character: CharacterInfo, required_scopes: array<int, string>, missing_scopes: array<int, string>}>
     */
    public function getMissingCharacterScopes(User $user): array
    {
        return collect($this->buildScopesArrayService->get($user))
            ->filter(fn (array $character_scopes): bool => $character_scopes['missing_scopes'] !== [])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function getMissingScopes(User $user): array
    {
        $scopes = $this->buildScopesArrayService
            ->get($user);

        return collect($scopes)
            ->pluck('missing_scopes')
            ->toArray();
    }
}
