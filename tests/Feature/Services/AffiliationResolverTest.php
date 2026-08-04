<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Seatplus\Auth\Enums\AffiliationType;
use Seatplus\Auth\Models\Permissions\Affiliation;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Services\Roles\AffiliationResolver;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

beforeEach(function () {
    Event::fake();

    $this->role = Role::create(['name' => 'resolver-test']);
});

function affiliate(int $entityId, string $type, AffiliationType $affiliationType): void
{
    Affiliation::create([
        'role_id' => test()->role->id,
        'affiliatable_id' => $entityId,
        'affiliatable_type' => $type,
        'type' => $affiliationType->value,
    ]);
}

// The existing factory-based parity suite always builds corps/alliances from a member character,
// so it can never exercise the two most dangerous divergences. These fixtures do.

it('includes a memberless corporation of an allowed alliance (corporation_infos.alliance_id, not character_affiliations)', function () {
    $alliance = AllianceInfo::factory()->create();
    $corp = CorporationInfo::factory()->create(['alliance_id' => $alliance->alliance_id]); // no synced members

    affiliate($alliance->alliance_id, AllianceInfo::class, AffiliationType::ALLOWED);

    expect((new AffiliationResolver)->coveredIds([$this->role->id], [$corp->corporation_id, $alliance->alliance_id]))
        ->toContain($corp->corporation_id)
        ->toContain($alliance->alliance_id);
});

it('lets forbidden win over a memberless corporation reachable through an allowed alliance', function () {
    $alliance = AllianceInfo::factory()->create();
    $corp = CorporationInfo::factory()->create(['alliance_id' => $alliance->alliance_id]);

    affiliate($alliance->alliance_id, AllianceInfo::class, AffiliationType::ALLOWED);
    affiliate($corp->corporation_id, CorporationInfo::class, AffiliationType::FORBIDDEN);

    expect((new AffiliationResolver)->coveredIds([$this->role->id], [$alliance->alliance_id, $corp->corporation_id]))
        ->toContain($alliance->alliance_id)
        ->not()->toContain($corp->corporation_id);
});

it('excludes a character present in character_affiliations but absent from character_infos (INNER JOIN parity)', function () {
    $corp = CorporationInfo::factory()->create();
    $phantom = CharacterAffiliation::factory()->create([
        'corporation_id' => $corp->corporation_id,
        'alliance_id' => null,
    ]);

    affiliate($corp->corporation_id, CorporationInfo::class, AffiliationType::ALLOWED);

    expect((new AffiliationResolver)->coveredIds([$this->role->id], [$corp->corporation_id, $phantom->character_id]))
        ->toContain($corp->corporation_id)
        ->not()->toContain($phantom->character_id);
});

it('covers only the requested ids that are affiliated', function () {
    $corp = CorporationInfo::factory()->create();
    affiliate($corp->corporation_id, CorporationInfo::class, AffiliationType::ALLOWED);

    $covered = (new AffiliationResolver)->coveredIds([$this->role->id], [$corp->corporation_id, 123456]);

    expect($covered)
        ->toContain($corp->corporation_id)
        ->not()->toContain(123456)
        ->and((new AffiliationResolver)->coveredIds([$this->role->id], []))->toBe([]);
});

it('covers requested ids against an inverse role without enumerating the universe', function () {
    // inverse on the test corporation → "everyone except that corp" is affiliated
    affiliate($this->test_character->corporation_id, CorporationInfo::class, AffiliationType::INVERSE);

    $insideInverted = $this->test_character->character_id;      // member of the inverted corp → NOT covered
    $outsideInverted = CharacterInfo::factory()->create()->character_id; // some other char → covered

    $covered = (new AffiliationResolver)->coveredIds(
        [$this->role->id],
        [$insideInverted, $outsideInverted],
    );

    expect($covered)
        ->toContain($outsideInverted)
        ->not()->toContain($insideInverted);
});

it('exposes a composable subquery per id-space for query scoping', function () {
    $corp = CorporationInfo::factory()->create();
    $alliance = AllianceInfo::factory()->create();
    $character = CharacterInfo::factory()->create();

    affiliate($corp->corporation_id, CorporationInfo::class, AffiliationType::ALLOWED);
    affiliate($alliance->alliance_id, AllianceInfo::class, AffiliationType::ALLOWED);
    affiliate($character->character_id, CharacterInfo::class, AffiliationType::ALLOWED);

    $resolver = new AffiliationResolver;
    $roleIds = [$this->role->id];

    expect(CorporationInfo::query()->whereIn('corporation_id', $resolver->corporationIdsSubquery($roleIds))->pluck('corporation_id')->all())
        ->toContain($corp->corporation_id)
        ->and(AllianceInfo::query()->whereIn('alliance_id', $resolver->allianceIdsSubquery($roleIds))->pluck('alliance_id')->all())
        ->toContain($alliance->alliance_id)
        ->and(CharacterInfo::query()->whereIn('character_id', $resolver->characterIdsSubquery($roleIds))->pluck('character_id')->all())
        ->toContain($character->character_id);
});
