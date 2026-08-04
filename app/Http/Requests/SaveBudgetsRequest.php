<?php

namespace App\Http\Requests;

use App\Models\WeeklyBudget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBudgetsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'week_start' => ['required', 'date_format:Y-m-d'],
            'budgets' => ['required', 'array'],
            'budgets.*.category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $this->user()->id),
            ],
            'budgets.*.budget_type' => ['required', Rule::in(WeeklyBudget::TYPES)],
            'budgets.*.target_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'save_as_template' => ['nullable', 'boolean'],
        ];
    }
}
