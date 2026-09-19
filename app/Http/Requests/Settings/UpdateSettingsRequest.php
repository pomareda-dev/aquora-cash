<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'density' => ['nullable', 'in:compact,comfortable'],
            'start_section' => ['nullable', 'in:dashboard,movements,categories,accounts,recurring'],
            'projection_horizon' => ['nullable', 'integer', 'between:1,24'],
            'debt_category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id))],
            'onboarding' => ['nullable', 'array'],
            'onboarding.completed_at' => ['nullable', 'date'],
            'onboarding.version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
