<?php

namespace App\Http\Requests\Cleaning;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update a task — every field is optional (`sometimes`); the client sends only
 * what changed. photo_required is still derived server-side from frequency.
 */
class UpdateCleaningTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route-level authorization handled by pizzasys
    }

    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'weight'      => ['sometimes', 'nullable', 'integer', 'between:0,100'],

            'frequency'      => ['sometimes', 'required', Rule::in(['daily', 'weekly', 'monthly', 'hourly'])],
            'interval'       => ['sometimes', 'integer', 'min:1', 'max:365'],
            'week_days'      => ['sometimes', 'nullable', 'array'],
            'week_days.*'    => ['integer', 'between:1,7'],
            'interval_hours' => ['sometimes', 'nullable', 'required_if:frequency,hourly', 'integer', 'between:1,24'],
            'starts_at'      => ['sometimes', 'required', 'date'],
            'ends_at'        => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'due_time'       => ['sometimes', 'nullable', 'date_format:H:i'],

            'store_ids'   => ['sometimes', 'required', 'array', 'min:1'],
            'store_ids.*' => ['integer', 'exists:stores,id'],
        ];
    }
}
