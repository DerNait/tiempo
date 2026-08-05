<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function rules(): array
    {
        $ownCategory = Rule::exists('categories', 'id')->where('user_id', $this->user()->id);

        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'week_starts_on' => ['sometimes', 'integer', 'between:1,7'],
            'accent_color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'audit_mode_enabled' => ['sometimes', 'boolean'],
            'audit_days' => ['sometimes', 'integer', 'between:1,60'],
            // A local calendar date, not an instant: the audit begins at
            // midnight of that day in the user's timezone.
            'audit_start_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'onboarded' => ['sometimes', 'boolean'],
            'rainmeter_priority_category_id' => ['sometimes', 'nullable', 'integer', $ownCategory],
            'rainmeter_leak_category_id' => ['sometimes', 'nullable', 'integer', $ownCategory],
        ];
    }
}
