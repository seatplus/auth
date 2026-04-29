<?php

declare(strict_types=1);

namespace Seatplus\Auth\Services\Roles\DTO;

use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

readonly class CriteriaData
{
    public function __construct(
        public int $entity_id,
        public string $entity_type,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            entity_id: (int) $data['entity_id'],
            entity_type: $data['entity_type'],
        );
    }

    public function entityClass(): string
    {
        return match ($this->entity_type) {
            'corporation' => CorporationInfo::class,
            'alliance' => AllianceInfo::class,
            default => throw new \ValueError("Unknown entity type: {$this->entity_type}"),
        };
    }
}
