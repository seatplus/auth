<?php

declare(strict_types=1);

namespace Seatplus\Auth\Services\Roles\DTO;

use Seatplus\Auth\Enums\AffiliationType;

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
}
