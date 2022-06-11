<?php

/*
 * MIT License
 *
 * Copyright (c) 2019, 2020, 2021 Felix Huber
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Seatplus\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Seatplus\Auth\DataTransferObjects\CheckPermissionAffiliationDto;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Pipelines\Middleware\CheckAffiliatedIdsPipe;
use Seatplus\Auth\Pipelines\Middleware\CheckOwnedAffiliatedIdsPipe;
use Seatplus\Auth\Services\Dtos\AffiliationsDto;

class CheckPermissionAffiliation
{
    private User $user;
    private Collection $requested_ids;
    private AffiliationsDto $affiliationsDto;

    private array $pipelines = [
        CheckOwnedAffiliatedIdsPipe::class,
        CheckAffiliatedIdsPipe::class,
    ];

    public function __construct(
    ) {
        //$this->user = User::find(auth()->user()->getAuthIdentifier());
    }

    /**
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $permissions, ?string $corporation_role = null)
    {

        // validate request and set requsted ids
        // we do this before fast tracking superuser to ensure superuser requests are valid too.

        $this->validateAndSetRequestedIds($request);

        if ($this->getUser()->can('superuser')) {
            return $next($request);
        }

        $checkPermissionAffiliationDto = new CheckPermissionAffiliationDto(
            requested_ids: $this->getRequestedIds(),
            affiliationsDto: $this->getAffiliationsDto($permissions, $corporation_role),
        );

        $all_requested_ids_validated = app(Pipeline::class)
            ->send($checkPermissionAffiliationDto)
            ->through($this->getPipelines())
            ->thenReturn()
            ->allIdsValidated();

        abort_unless($all_requested_ids_validated, 401, 'You are not allowed to access the requested entity');

        return $next($request);
    }

    private function validateAndSetRequestedIds(Request $request) : void
    {
        $current_payload = $request->input();
        $route_parameters = $request->route()->parameters();

        $constructed_payload = collect($current_payload)
            ->merge($route_parameters)
            ->unique()
            ->toArray();

        $validator = Validator::make($constructed_payload, [
            'character_id' => ['required_without_all:corporation_id,alliance_id,character_ids,corporation_ids,alliance_ids', 'integer'],
            'corporation_id' => ['required_without_all:character_id,alliance_id,character_ids,corporation_ids,alliance_ids', 'integer'],
            'alliance_id' => ['required_without_all:character_id,corporation_id,character_ids,corporation_ids,alliance_ids', 'integer'],
            'character_ids' => ['required_without_all:character_id,corporation_id,alliance_id,corporation_ids,alliance_ids', 'array'],
            'corporation_ids' => ['required_without_all:character_id,corporation_id,alliance_id,character_ids,alliance_ids', 'array'],
            'alliance_ids' => ['required_without_all:character_id,corporation_id,alliance_id,character_ids,corporation_ids', 'array'],
        ]);

        abort_if($validator->fails(), 403, implode(', ', $validator->errors()->all()));

        $this->requested_ids = collect($validator->validated())->flatten()->unique();
    }

    /**
     * @return array|string[]
     */
    public function getPipelines(): array
    {
        return $this->pipelines;
    }

    public function getRequestedIds(): Collection
    {
        return $this->requested_ids;
    }

    public function getUser(): User
    {
        return User::find(auth()->user()->getAuthIdentifier());
    }

    public function getAffiliationsDto(string $permissions, ?string $character_role = null): AffiliationsDto
    {
        return new AffiliationsDto(
            user: $this->getUser(),
            permission: $permissions,
            corporation_roles: is_string($character_role) ? explode('|', $character_role) : null
        );
    }
}
