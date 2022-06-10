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
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Seatplus\Auth\DataTransferObjects\CheckPermissionAffiliationDto;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Pipelines\Middleware\AddOwnedCharacterIdsPipe;
use Seatplus\Auth\Pipelines\Middleware\CheckAffiliatedIdsPipe;
use Seatplus\Auth\Pipelines\Middleware\CheckOwnedAffiliatedIdsPipe;
use Seatplus\Auth\Services\Affiliations\GetOwnedCharacterAffiliationsService;
use Seatplus\Auth\Services\Dtos\AffiliationsDto;
use Seatplus\Web\Services\GetRecruitIdsService;
use Seatplus\Web\Services\HasCharacterNecessaryRole;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;

class CheckPermissionAffiliation
{
    private User $user;
    private Collection $requested_ids;
    private Collection $validated_ids;
    private AffiliationsDto $affiliationsDto;
    private string $request_type;

    private array $pipelines = [
        CheckOwnedAffiliatedIdsPipe::class,
        CheckAffiliatedIdsPipe::class
    ];

    public function __construct(
    )
    {
        $this->user = User::find(auth()->user()->getAuthIdentifier());
        $this->validated_ids = collect();
    }

    /**
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $permissions, ?string $corporation_role = null)
    {

        // validate request and set requsted ids
        // we do this before fast tracking superuser to ensure superuser requests are valid too.

        $this->setRequestType($corporation_role);

        $this->validateAndSetRequestedIds($request);

        if ($this->getUser()->can('superuser')) {

            return $next($request);
        }



        $this->setAffiliationsDto($permissions, $corporation_role);

        $checkPermissionAffiliationDto = new CheckPermissionAffiliationDto(
            requested_ids: $this->getRequestedIds(),
            request_type: $this->getRequestType(),
            affiliationsDto: $this->getAffiliationsDto(),
        );

        $all_requested_ids_validated = app(Pipeline::class)
            ->send($checkPermissionAffiliationDto)
            ->through($this->getPipelines())
            ->thenReturn()
            ->allIdsValidated();

        abort_unless($all_requested_ids_validated, 401, 'You are not allowed to access the requested entity');

        return $next($request);

        // First do owned affiliations

        // Second do recruits

        // Third do all other affiliations


       /* if ($requested_ids->isEmpty()) {
            abort_unless($this->assertIfUserHasRequiredPermissionOrCharacterRole($permissions, $character_role), 403, 'You do not have the necessary permission to perform this action.');

            return $next($request);
        }*/

        /*$this->buildAffiliatedIdsByPermissions($permissions);

        $this->buildRecruitIds();

        if (is_string($corporation_role)) {
            $this->buildAffiliatedIdsByCharacterRole($corporation_role);
        }

        $validated_ids = $requested_ids->intersect($this->getAffiliatedIds()->toArray());

        abort_unless($validated_ids->isNotEmpty(), 403, 'You are not allowed to access the requested entity');

        $this->appendValidatedIds($request->query, $validated_ids);

        return $next($request);*/
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

    private function checkPermissionForRequestedId() : bool
    {

        if($this->checkOwnedAffiliations()) {

            return true;
        }

        if($this->isCharacterRequestType() && $this->getAffiliationsDto()->user->can('can accept or deny applications')) {

        }

    }

    private function checkOwnedAffiliations() : bool
    {

        $validated_ids = GetOwnedCharacterAffiliationsService::make($this->getAffiliationsDto())
            ->getQuery()
            ->when($this->isCharacterRequestType(), fn(Builder $query) => $query->pluck('character_id'))
            ->when($this->isCorporationRequestType(), fn(Builder $query) => $query->pluck('corporation_id'))
            ->intersect($this->getRequestedIds());

        $this->mergeValidatedIds($validated_ids);


    }

    /**
     * @return array|string[]
     */
    public function getPipelines(): array
    {
        return $this->pipelines;
    }

    private function mergeValidatedIds(Collection|array $new_validated_ids)
    {
        $this->validated_ids = $this->validated_ids->merge($new_validated_ids);
    }

    private function allRequestedIdsValidated() : bool
    {
        $count_of_intersect = $this->getRequestedIds()->intersect($this->validated_ids);

        return $count_of_intersect->count() === $this->getRequestedIds()->count();
    }

    public function getAffiliatedIds(): Collection
    {
        return $this->affiliated_ids
            ->flatten()
            ->unique();
    }

    public function getRequestedIds(): Collection
    {
        return $this->requested_ids;
    }

    /**
     * @return string
     */
    public function getRequestType(): string
    {
        return $this->request_type;
    }

    private function isCharacterRequestType() : bool
    {
        return $this->getRequestType() === 'character';
    }

    private function isCorporationRequestType() : bool
    {
        return $this->getRequestType() === 'corporation';
    }

    /**
     * @param string $request_type
     */
    public function setRequestType(?string $corporation_roles): void
    {
        $this->request_type = is_null($corporation_roles) ? 'character' : 'corporation';
    }

    private function checkUserPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->getUser()->can($permission)) {
                return true;
            }
        }

        return false;
    }

    private function buildAffiliatedIdsByPermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            $affiliatedIds = getAffiliatedIdsByPermission($permission);

            $this->affiliated_ids->push($affiliatedIds);
        }
    }

    private function buildRecruitIds(): void
    {
        $recruit_ids = GetRecruitIdsService::get();

        $this->affiliated_ids->push($recruit_ids);
    }

    private function assertIfUserHasRequiredPermissionOrCharacterRole(array $permissions, ?string $character_role)
    {
        if ($this->checkUserPermissions($permissions)) {
            return true;
        }

        if (is_null($character_role)) {
            return false;
        }

        return $this->checkUserCharacterRoles($character_role);
    }

    private function checkUserCharacterRoles(?string $character_role): bool
    {
        if (is_null($character_role)) {
            return false;
        }

        return empty($this->buildAffiliatedIdsByCharacterRole($character_role)) ? false : true;
    }

    private function buildAffiliatedIdsByCharacterRole(string $character_role): array
    {
        $affiliated_ids_from_character_role = $this->getUser()
            ->load(['characters.roles', 'characters.corporation'])
            ->characters
            ->map(
                fn ($character) => HasCharacterNecessaryRole::check($character, $character_role)
                ? $character->corporation->corporation_id
                : false
            )
            ->filter()
            ->unique()
            ->toArray();

        $this->affiliated_ids->push($affiliated_ids_from_character_role);

        return $affiliated_ids_from_character_role;
    }

    private function appendValidatedIds(ParameterBag $bag, Collection $validated_ids)
    {
        $bag->add(['validated_ids' => $validated_ids->all()]);
    }

    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @return AffiliationsDto
     */
    public function getAffiliationsDto(): AffiliationsDto
    {
        return $this->affiliationsDto;
    }

    public function setAffiliationsDto(string $permissions, ?string $character_role = null): void
    {
        $this->affiliationsDto = new AffiliationsDto(
            user: $this->getUser(),
            permission: $permissions,
            corporation_roles: is_string($character_role) ? explode('|', $character_role) : null
        );
    }
}
