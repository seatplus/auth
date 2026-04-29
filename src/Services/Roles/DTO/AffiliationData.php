<?php

declare(strict_types=1);

namespace Seatplus\Auth\Services\Roles\DTO;

use Seatplus\Auth\Enums\AffiliationType;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

readonly class AffiliationData
{
    public function __construct(
        public int $entity_id,
        public string $entity_type,
        public AffiliationType $affiliation_type,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            entity_id: (int) $data['entity_id'],
            entity_type: $data['entity_type'],
            affiliation_type: AffiliationType::from($data['affiliation_type']),
        );
    }

    public function entityClass(): string
    {
        return match ($this->entity_type) {
            'character' => CharacterInfo::class,
            'corporation' => CorporationInfo::class,
            'alliance' => AllianceInfo::class,
            default => throw new \ValueError("Unknown entity type: {$this->entity_type}"),
        };
    }
}
