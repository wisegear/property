<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SchoolSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'rating' => ['nullable', 'in:1,2,3,4,not_judged,no_grade'],
            'phase' => ['nullable', 'string', 'max:100'],
            'local_authority' => ['nullable', 'string', 'max:150'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'q.max' => 'Search terms must be 100 characters or fewer.',
            'rating.in' => 'Choose a valid Ofsted rating.',
        ];
    }
}
