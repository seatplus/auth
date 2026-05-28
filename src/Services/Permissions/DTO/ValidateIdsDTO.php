<?php

declare(strict_types=1);

namespace Seatplus\Auth\Services\Permissions\DTO;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ValidateIdsDTO
{
    public function __construct(
        private readonly ?int $character_id = null,
        private readonly ?int $corporation_id = null,
        private readonly ?int $alliance_id = null,
        private readonly ?array $character_ids = null,
        private readonly ?array $corporation_ids = null,
        private readonly ?array $alliance_ids = null
    ) {}

    public static function fromRequest(Request $request): ValidateIdsDTO
    {
        $all_data = [...$request->all(), ...$request->route()->parameters()];

        $toInt = fn (mixed $v): ?int => $v !== null ? (int) $v : null;
        $toIntArray = fn (mixed $v): ?array => $v !== null ? array_map('intval', (array) $v) : null;

        return new self(
            character_id: $toInt(Arr::get($all_data, 'character_id')),
            corporation_id: $toInt(Arr::get($all_data, 'corporation_id')),
            alliance_id: $toInt(Arr::get($all_data, 'alliance_id')),
            character_ids: $toIntArray(Arr::get($all_data, 'character_ids')),
            corporation_ids: $toIntArray(Arr::get($all_data, 'corporation_ids')),
            alliance_ids: $toIntArray(Arr::get($all_data, 'alliance_ids')),
        );
    }

    /*public static function make(...$args): ValidateIdsDTO
    {
        return new self(...$args);
    }*/

    /**
     * @throws ValidationException
     */
    public function get(): array
    {

        // if any of the constructor parameters is not null, we return the validated array
        if (! array_filter(get_object_vars($this), fn (null|int|array $value) => ! is_null($value))) {
            return [];
        }

        return collect($this->validate())
            ->flatten()
            ->map(fn (string|int $value) => (int) $value)
            ->all();
    }

    /**
     * @throws ValidationException
     */
    private function validate(): array
    {
        $ids = collect([
            'character_id' => $this->character_id,
            'corporation_id' => $this->corporation_id,
            'alliance_id' => $this->alliance_id,
            'character_ids' => $this->character_ids,
            'corporation_ids' => $this->corporation_ids,
            'alliance_ids' => $this->alliance_ids,
        ])->filter()->all();

        $keys = [
            'character_id', 'character_ids',
            'corporation_id', 'corporation_ids',
            'alliance_id', 'alliance_ids',
        ];

        $presentKeys = array_filter($keys, fn (string $key) => ! is_null($ids[$key] ?? null));

        abort_unless(count($presentKeys) === 1, 403, 'Exactly one of the parameters ['.implode(', ', $keys).'] must be present.');

        $validator = Validator::make($ids, [
            'character_id' => 'nullable|integer',
            'character_ids' => 'nullable|array',
            'character_ids.*' => 'integer',
            'corporation_id' => 'nullable|integer',
            'corporation_ids' => 'nullable|array',
            'corporation_ids.*' => 'integer',
            'alliance_id' => 'nullable|integer',
            'alliance_ids' => 'nullable|array',
            'alliance_ids.*' => 'integer',
        ]);

        abort_if($validator->fails(), 403, implode(', ', $validator->errors()->all()));

        return $validator->validated();
    }
}
