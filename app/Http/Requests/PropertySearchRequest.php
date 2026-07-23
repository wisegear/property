<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PropertySearchRequest extends FormRequest
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
            'postcode' => [
                $this->expectsJson() ? 'required' : 'nullable',
                'string',
                'max:8',
                'regex:/^[A-Z]{1,2}\d[A-Z\d]?\s?\d[A-Z]{2}$/i',
            ],
            'sort' => ['nullable', 'string'],
            'dir' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'postcode.required' => 'Enter a UK postcode.',
            'postcode.regex' => 'Enter a valid UK postcode, for example SW7 5PH.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('postcode')) {
            $postcode = strtoupper(preg_replace('/\s+/', '', trim((string) $this->input('postcode'))));

            $this->merge([
                'postcode' => strlen($postcode) >= 5
                    ? substr($postcode, 0, -3).' '.substr($postcode, -3)
                    : $postcode,
            ]);
        }
    }
}
