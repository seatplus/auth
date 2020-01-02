<?php

namespace Seatplus\Auth\Actions;

use Illuminate\Support\Str;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;

class GetAffiliatedCharactersIdsByPermissionArray
{
    private $permission;

    /**
     * @var \Illuminate\Contracts\Auth\Authenticatable|null
     */
    private $user;

    /**
     * @var \Illuminate\Support\Collection
     */
    private $result;

    /**
     * @var string
     */
    private $cache_key;

    /**
     * @return string
     */
    public function getCacheKey(): string
    {

        return $this->cache_key;
    }

    public function __construct($permission)
    {
        $this->permission = $permission;
        $this->user = auth()->user();
        $this->result = collect();
        $this->cache_key = 'affiliated_character_ids_by_permission:'.$this->permission;
    }

    public function execute() :array
    {
        try {
            return cache($this->cache_key) ?? $this->getAffiliatedCharacterIds()
                    ->addOwnedCharacterIds()
                    ->getResult();
        } catch (\Exception $e) {
            report($e);
        }

        return [];
    }

    /**
     * @throws \Exception
     *
     * @return array
     */
    private function getResult(): array
    {
        cache([
            $this->cache_key => $this->result->unique()->toArray(),
        ], now()->addMinutes(5));

        return cache($this->cache_key);
    }

    private function getAffiliatedCharacterIds()
    {
        // start by asserting that the user has the required permission and id is set
        if ($this->user->hasPermissionTo($this->permission)) {
            $this->user->roles->filter(function (Role $role) {

                return $role->hasPermissionTo($this->permission);
            })->map(function (Role $role) {

                return $role->buildAffiliatedIds()->getAffiliatedIds()->all();
            })->flatten()->filter()->each(function ($affiliated_id) {

                $this->result->push($affiliated_id);
            });
        }

        return $this;
    }


    private function addOwnedCharacterIds()
    {
        $this->user->characters->pluck('character_id')->each(function ($character_id) {
            $this->result->push($character_id);
        });

        return $this;
    }
}
