<?php

declare(strict_types=1);

namespace Seatplus\Auth\Services\SsoScopes;

use Seatplus\Eveapi\Models\SsoScopes;

class GlobalSsoScopesService
{
    public function set(array $scopes): void
    {
        SsoScopes::query()->create([
            'selected_scopes' => $scopes,
            'type' => 'global',
        ]);
    }

    /**
     * The installation-wide required scopes, as a flat list.
     *
     * `selected_scopes` is itself an array, so plucking the column yields one nested array per row.
     * Callers that diff scopes flattened defensively; RedirectSSOController merges the value straight
     * into Socialite's scope list, where formatScopes() does implode(' ', $scopes) — a nested array
     * there raises "Array to string conversion", which Laravel promotes to an ErrorException. So any
     * installation that configured an instance-wide requirement got a 500 on sign-in and on
     * add-character.
     *
     * @return array<int, string>
     */
    public function get(): array
    {
        return SsoScopes::query()
            ->where('type', 'global')
            ->pluck('selected_scopes')
            ->flatten()
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
