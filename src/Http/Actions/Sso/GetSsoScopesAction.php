<?php

namespace Seatplus\Auth\Http\Actions\Sso;

class GetSsoScopesAction
{
    public function execute()
    {

        // TODO: Refactor this using session boolean which scopes to provide
        /*if(is_array($scopes) && ! empty($scopes))
            return $scopes;*/

        return config('eveapi.scopes.maximum');

    }
}
