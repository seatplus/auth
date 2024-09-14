<?php

namespace Seatplus\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Seatplus\Auth\Enums\AffiliationType;

class RoleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'role_id' => 'required|integer',
            'name' => 'nullable|string',
            'affiliated' => 'nullable|array',
            'affiliated.*.entity_id' => 'required|integer',
            'affiliated.*.entity_type' => ['required', 'string', Rule::in(['character', 'corporation', 'alliance'])],
            'affiliated.*.affiliation_type' => [
                'required',
                'string',
                Rule::in(array_map(fn (AffiliationType $affiliationType) => $affiliationType->value, AffiliationType::cases())),
            ],
            'assigned' => 'nullable|array',
            'assigned.*.entity_id' => 'required|integer',
            'assigned.*.entity_type' => ['required', 'string', Rule::in(['character', 'corporation', 'alliance'])],
            'assigned.*.can_moderate' => 'nullable|boolean',
        ];
    }
}
