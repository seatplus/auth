<?php

declare(strict_types=1);

namespace Seatplus\Auth\Services\SsoScopes;

use Illuminate\Database\Eloquent\Builder;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\SsoScopes;

class BuildScopesArrayService
{
    /** @var array<int|string, string|array<int|string, string|list<string>>> */
    const array USER_RELATIONS = [
        'characters' => self::CHARACTER_RELATIONS,
        'application.corporation' => ['ssoScopes', 'alliance.ssoScopes'],
    ];

    /** @var array<int|string, list<string>|string> */
    const array CHARACTER_RELATIONS = [
        'alliance.ssoScopes',
        'corporation.ssoScopes',
        'application.corporation' => ['ssoScopes', 'alliance.ssoScopes'],
        'refreshToken',
    ];

    public function __construct(
        private readonly bool $withApplicationScopes = true,
        private readonly GlobalSsoScopesService $globalSsoScopesService = new GlobalSsoScopesService,
    ) {}

    private function getUserRequiredScopes(User $user): array
    {
        $user = $user->loadMissing(self::USER_RELATIONS);

        $required_scopes = $this->getUserScopes($user);

        if ($this->isWithApplicationScopes()) {
            $required_scopes['user_application_corporation_scopes'] = $user->application->corporation->ssoScopes->selected_scopes ?? [];
            $required_scopes['user_application_alliance_scopes'] = $user->application->corporation->alliance->ssoScopes->selected_scopes ?? [];
        }

        return collect($required_scopes)
            ->flatten(1)
            ->filter()
            ->unique()
            ->flatten(1)
            ->toArray();
    }

    private function getGlobalScopes(): array
    {
        return $this->globalSsoScopesService->get();
    }

    private function getUserScopes(User $user): array
    {
        // get all corporation and alliance ids
        $corporation_ids = $user->characters->pluck('corporation.corporation_id')->unique()->all();
        $alliance_ids = $user->characters->pluck('alliance.alliance_id')->filter()->unique()->all();

        // get all scopes for the corporations and alliances
        return SsoScopes::query()
            ->whereIn('morphable_id', [...$corporation_ids, ...$alliance_ids])
            ->where('type', 'user')
            ->pluck('selected_scopes')
            ->flatten()
            ->unique()
            ->toArray();
    }

    private function build(User $user): array
    {
        $user_required_scopes = $this->getUserRequiredScopes($user);

        return $user->characters
            ->map(function (CharacterInfo $character) use ($user_required_scopes) {

                $required_scopes = [...$user_required_scopes, ...$this->getCharacterRequiredScopes($character)];
                $token_scopes = $character->refreshToken->scopes ?? [];

                return [
                    'character' => $character,
                    'required_scopes' => $required_scopes,
                    // Reindexed: array_diff preserves keys, so a gap made this a sparse array that
                    // json_encode emits as an object rather than a list.
                    'missing_scopes' => array_values(array_diff($required_scopes, $token_scopes)),
                ];
            })
            ->toArray();
    }

    private function getCharacterRequiredScopes(CharacterInfo $character): array
    {
        $character = $character->loadMissing(self::CHARACTER_RELATIONS);

        $required_scopes = [
            'corporation_scopes' => $character->corporation->ssoScopes->selected_scopes ?? [],
            'alliance_scopes' => $character->alliance->ssoScopes->selected_scopes ?? [],
            'global_scope' => $this->getGlobalScopes(),
        ];

        if ($this->isWithApplicationScopes()) {
            $required_scopes['character_application_corporation_scopes'] = $character->application->corporation->ssoScopes->selected_scopes ?? [];
            $required_scopes['character_application_alliance_scopes'] = $character->application->corporation->alliance->ssoScopes->selected_scopes ?? [];
        }

        return collect($required_scopes)
            ->flatten(1)
            ->filter()
            ->unique()
            ->flatten(1)
            ->toArray();
    }

    private function isWithApplicationScopes(): bool
    {
        return $this->withApplicationScopes;
    }

    public function get(User|CharacterInfo $entity): array
    {
        // A User identifies itself; only a CharacterInfo has to be resolved to its owner. Constraining
        // the query only in the CharacterInfo case left the User case unfiltered, so ->first() returned
        // whichever user sorted first and every caller passing a User — IsUserCompliantService::check()
        // always does — evaluated somebody else's tokens.
        $user = $entity instanceof User
            ? $entity
            : User::query()
                ->whereHas('characters', fn (Builder $query) => $query
                    ->where('character_infos.character_id', $entity->character_id)
                )
                ->first();

        if ($user === null) {
            return [];
        }

        // build() -> getUserRequiredScopes() already loadMissing()es USER_RELATIONS.
        return $this->build($user);
    }
}
