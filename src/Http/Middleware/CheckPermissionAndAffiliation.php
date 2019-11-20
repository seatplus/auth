<?php

namespace Seatplus\Auth\Http\Middleware;

use Closure;

class CheckPermissionAndAffiliation
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next, $permission)
    {
        if (app('auth')->guest()) {
            abort(403, 'User is not logged in.');
        }

        if (collect($request->route()->parameters())->intersectByKeys([
            'character_id' => '', 'corporation_id' => '', 'alliance_id' => '',
        ])->isEmpty()) {
            abort(403, 'Missing required route parameter.');
        }

        // if the character belongs to the user, no permissions are required.
        if ($request->character_id && in_array($request->character_id, auth()->user()->characters->pluck('character_id')->toArray())) {
            return $next($request);
        }

        // if the character or corporation is not provided by the user, check permissions
        $permissions = is_array($permission)
            ? $permission
            : explode('|', $permission);

        $id_to_check_affiliation = ($id = reset($request->route()->parameters)) ? intval($id) : null;

        foreach ($permissions as $permission) {
            if ((auth()->user()->hasAccessTo($permission, $id_to_check_affiliation))) {
                return $next($request);
            }
        }

        return abort(403, 'Missing Permission: '.$permission);
    }
}
