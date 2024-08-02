<?php

namespace Seatplus\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Seatplus\Auth\Enums\AffiliationType;

class RoleRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'role_id' => 'required|integer',
            'affiliated' => 'nullable|array',
            'affiliated.*.entity_id' => 'required|integer',
            'affiliated.*.entity_type' => 'required|string',
            'affiliated.*.affiliation_type' => [
                'required',
                'string',
                Rule::in(array_map(fn(AffiliationType $affiliationType) => $affiliationType->value, AffiliationType::cases()))
            ],
            'assigned' => 'nullable|array',
            'assigned.*.entity_id' => 'required|integer',
            'assigned.*.entity_type' => ['required', 'string', Rule::in(['corporation', 'alliance'])],
            'assigned.*.can_moderate' => 'nullable|boolean',
        ];
    }
}
