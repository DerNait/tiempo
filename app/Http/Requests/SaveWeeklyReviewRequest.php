<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveWeeklyReviewRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'week_start' => ['required', 'date_format:Y-m-d'],
            'biggest_time_leak' => ['nullable', 'string', 'max:2000'],
            'most_neglected_priority' => ['nullable', 'string', 'max:2000'],
            'what_worked' => ['nullable', 'string', 'max:2000'],
            'what_did_not_work' => ['nullable', 'string', 'max:2000'],
            'next_week_adjustment' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
