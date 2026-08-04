<?php

namespace App\Http\Requests;

use App\Models\TimeEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTimeEntryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'category_id' => [
                'sometimes',
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $this->user()->id),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'started_at' => ['sometimes', 'date'],
            'ended_at' => ['sometimes', 'nullable', 'date'],
            'source' => ['sometimes', Rule::in(TimeEntry::SOURCES)],
        ];
    }
}
