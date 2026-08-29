<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MonthlySnapshotMapPointsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'e_min' => ['required', 'integer', 'between:-2000000,2000000'],
            'e_max' => ['required', 'integer', 'between:-2000000,2000000', 'gt:e_min'],
            'n_min' => ['required', 'integer', 'between:-2000000,3000000'],
            'n_max' => ['required', 'integer', 'between:-2000000,3000000', 'gt:n_min'],
            'limit' => ['sometimes', 'integer', 'between:1,2500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'e_max.gt' => 'The maximum easting must be greater than the minimum easting.',
            'n_max.gt' => 'The maximum northing must be greater than the minimum northing.',
        ];
    }
}
