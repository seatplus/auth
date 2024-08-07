<?php

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

    public function get(): array
    {
        return SsoScopes::query()
            ->where('type', 'global')
            ->pluck('selected_scopes')
            ->toArray();
    }

}
