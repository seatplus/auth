<?php

namespace Seatplus\Auth\Http\Actions\Sso;

class GetSsoScopesAction
{
    public function execute()
    {
        $scopes = config('eveapi.scopes.selected');

        if (is_array($scopes) && !empty($scopes)) {
            return $scopes;
        }

        //return ['publicData', 'esi-characters.read_titles.v1'];
        return config('eveapi.scopes.minimum');
    }
}
