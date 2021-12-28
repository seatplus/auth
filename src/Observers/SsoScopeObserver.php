<?php

namespace Seatplus\Auth\Observers;

use Illuminate\Support\Facades\Cache;
use Seatplus\Eveapi\Models\SsoScopes;

class SsoScopeObserver
{

    public function created(SsoScopes $ssoScopes)
    {
        $this->flushCache();
    }

    public function updated(SsoScopes $ssoScopes)
    {
        $this->flushCache();
    }

    public function deleted(SsoScopes $ssoScopes)
    {
        $this->flushCache();
    }

    private function flushCache()
    {
        Cache::tags(['characters_with_missing_scopes'])->flush();
    }
}