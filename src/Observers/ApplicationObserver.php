<?php

namespace Seatplus\Auth\Observers;

use Illuminate\Support\Facades\Cache;
use Seatplus\Auth\Models\CharacterUser;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Models\Application;
use Seatplus\Eveapi\Models\Character\CharacterInfo;

class ApplicationObserver
{
    public function created(Application $application)
    {
        $user_id = match ($application->applicationable_type) {
            User::class => $application->applicationable_id,
            CharacterInfo::class => CharacterUser::query()->firstWhere('character_id', $application->applicationable_id)->user_id
        };

        Cache::tags(['characters_with_missing_scopes', $user_id])->flush();
    }
}