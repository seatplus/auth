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
            return cache($this->cache_key) ?? $this->getInversedCharacterIds()
                    ->addAllowedCharacterIds()
                    ->removeForbiddenCharacterIds()
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

    private function getInversedCharacterIds()
    {
        $character_ids = $this->getTypedAffiliatedIdsArrayByPermission('character_ids', 'inverse');
        $corporation_ids = $this->getTypedAffiliatedIdsArrayByPermission('corporation_ids', 'inverse');
        $alliance_ids = $this->getTypedAffiliatedIdsArrayByPermission('alliance_ids', 'inverse');

        if (!empty($character_ids) || !empty($corporation_ids) || !empty($alliance_ids)) {
            CharacterAffiliation::query()
                ->select('character_affiliations.character_id')
                //->orWhereNotIn('character_id', $character_ids)
                ->when(!empty($character_ids), function ($query) use ($character_ids) {
                    return $query->orWhereNotIn('character_affiliations.character_id', $character_ids);
                })
                ->when(!empty($corporation_ids), function ($query) use ($corporation_ids) {
                    return $query->orWhereNotIn('corporation_id', $corporation_ids);
                })
                ->when(!empty($alliance_ids), function ($query) use ($alliance_ids) {
                    return $query->orWhereNotIn('alliance_id', $alliance_ids);
                })
                ->join('character_infos', 'character_affiliations.character_id', '=', 'character_infos.character_id')
                ->pluck('character_id')
                ->each(function ($character_id) {
                    $this->result->push($character_id);
                });
        }

        return $this;
    }

    private function getTypedAffiliatedIdsArrayByPermission(string $category, string $type) : array
    {
        if (!Str::contains($type, ['allowed', 'forbidden', 'inverse'])) {
            throw new \Exception('type should be either allowed, forbidden or inverse');
        }
        if (!Str::contains($category, ['character_ids', 'corporation_ids', 'alliance_ids'])) {
            throw new \Exception('category should be either character_ids, corporation_ids or alliance_ids');
        }
        // start by asserting that the user has the required permission and id is set
        if (!$this->user->hasPermissionTo($this->permission)) {
            return [];
        }

        return $this->user->roles->filter(function (Role $role) {
            return $role->hasPermissionTo($this->permission);
        })->map(function ($role) use ($type, $category) {
            if (isset($role->affiliations->$type) && property_exists($role->affiliations->$type, $category)) {
                return $role->affiliations->$type->$category;
            }

            return [];
        })->flatten()->filter()->all();
    }

    private function addOwnedCharacterIds()
    {
        $this->user->characters->pluck('character_id')->each(function ($character_id) {
            $this->result->push($character_id);
        });

        return $this;
    }

    private function addAllowedCharacterIds()
    {
        $character_ids = $this->getTypedAffiliatedIdsArrayByPermission('character_ids', 'allowed');
        $corporation_ids = $this->getTypedAffiliatedIdsArrayByPermission('corporation_ids', 'allowed');
        $alliance_ids = $this->getTypedAffiliatedIdsArrayByPermission('alliance_ids', 'allowed');

        if (!empty($character_ids) || !empty($corporation_ids) || !empty($alliance_ids)) {
            CharacterAffiliation::query()
                ->select('character_affiliations.character_id')
                //->orWhereNotIn('character_id', $character_ids)
                ->when(!empty($character_ids), function ($query) use ($character_ids) {
                    return $query->orWhereIn('character_affiliations.character_id', $character_ids);
                })
                ->when(!empty($corporation_ids), function ($query) use ($corporation_ids) {
                    return $query->orWhereIn('corporation_id', $corporation_ids);
                })
                ->when(!empty($alliance_ids), function ($query) use ($alliance_ids) {
                    return $query->orWhereIn('alliance_id', $alliance_ids);
                })
                ->join('character_infos', 'character_affiliations.character_id', '=', 'character_infos.character_id')
                ->pluck('character_id')
                ->each(function ($character_id) {
                    $this->result->push($character_id);
                });
        }

        return $this;
    }

    private function removeForbiddenCharacterIds()
    {
        $character_ids = $this->getTypedAffiliatedIdsArrayByPermission('character_ids', 'forbidden');
        $corporation_ids = $this->getTypedAffiliatedIdsArrayByPermission('corporation_ids', 'forbidden');
        $alliance_ids = $this->getTypedAffiliatedIdsArrayByPermission('alliance_ids', 'forbidden');

        if (!empty($character_ids) || !empty($corporation_ids) || !empty($alliance_ids)) {
            $ids_to_remove = CharacterAffiliation::query()
                ->select('character_affiliations.character_id')
                //->orWhereNotIn('character_id', $character_ids)
                ->when(!empty($character_ids), function ($query) use ($character_ids) {
                    return $query->orWhereIn('character_affiliations.character_id', $character_ids);
                })
                ->when(!empty($corporation_ids), function ($query) use ($corporation_ids) {
                    return $query->orWhereIn('corporation_id', $corporation_ids);
                })
                ->when(!empty($alliance_ids), function ($query) use ($alliance_ids) {
                    return $query->orWhereIn('alliance_id', $alliance_ids);
                })
                ->join('character_infos', 'character_affiliations.character_id', '=', 'character_infos.character_id')
                ->pluck('character_id')
                ->all();
        }

        if (isset($ids_to_remove)) {
            $this->result = $this->result->reject(function ($value) use ($ids_to_remove) {
                return in_array($value, $ids_to_remove);
            });
        }

        return $this;
    }
}
