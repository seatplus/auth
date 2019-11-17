<?php

namespace Seatplus\Auth\Http\Actions\Sso;

class GetSsoScopesAction
{
    public function execute()
    {
        $scopes = setting('sso_scopes', true);

        if(is_array($scopes))
            return $scopes;

        //return ['publicData', 'esi-characters.read_titles.v1'];
        return config('eveapi.scopes.maximum');
    }
}
