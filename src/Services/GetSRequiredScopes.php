<?php


namespace Seatplus\Auth\Services;


use Illuminate\Support\Collection;

class GetSRequiredScopes
{
    /**
     * @var \Illuminate\Support\Collection
     */
    private Collection $scopes;

    /**
     * @var \Illuminate\Support\Collection
     */
    private Collection $characters;

    public function __construct()
    {
        $this->scopes = collect(config('eveapi.scopes.minimum'));

    }

    public function execute()
    {

        if(auth()->guest())
            return $this->scopes;

        $this->characters = (new GetCharactersWithRequiredSsoScopes)->execute();

        return $this->scopes
            ->merge((new GetRequiredScopesFromCharacters)->execute($this->characters))
            ->unique()
            ->filter();
    }

}
