<?php

declare(strict_types=1);

namespace Seatplus\Auth\Services\Roles\DTO;

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
}
