<?php

namespace App\Http\Requests\Cleaning;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCleaningTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level authorization is handled by pizzasys via the auth middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'weight'      => ['nullable', 'integer', 'between:0,100'],

            // recurrence rule
            'frequency'      => ['required', Rule::in(['daily', 'weekly', 'monthly', 'hourly'])],
            'interval'       => ['nullable', 'integer', 'min:1', 'max:365'],
            'week_days'      => ['nullable', 'array'],
            'week_days.*'    => ['integer', 'between:1,7'],
            'interval_hours' => ['nullable', 'required_if:frequency,hourly', 'integer', 'between:1,24'],
            'starts_at'      => ['required', 'date'],
            'ends_at'        => ['nullable', 'date', 'after_or_equal:starts_at'],
            'due_time'       => ['nullable', 'date_format:H:i'],

            'store_ids'   => ['required', 'array', 'min:1'],
            'store_ids.*' => ['integer', 'exists:stores,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'interval_hours.required_if' => 'For "Every X hours" tasks you must set how many hours.',
            'store_ids.min'              => 'Assign the task to at least one store.',
        ];
    }
}
