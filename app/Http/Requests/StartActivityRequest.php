<?php

namespace App\Http\Requests;

use App\Models\TimeEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartActivityRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $this->user()->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', Rule::in(TimeEntry::SOURCES)],
            'started_at' => ['nullable', 'date'],
        ];
    }
}
