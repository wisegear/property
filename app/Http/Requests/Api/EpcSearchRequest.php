<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EpcSearchRequest extends FormRequest
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
            'nation' => ['required', 'string', Rule::in(['england-wales', 'scotland'])],
            'postcode' => ['required', 'string', 'max:16', 'regex:/^[A-Z]{1,2}\d[A-Z\d]?\s?\d[A-Z]{2}$/'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $postcode = strtoupper(preg_replace('/\s+/', '', (string) $this->query('postcode', '')));

        if (strlen($postcode) >= 5) {
            $postcode = substr($postcode, 0, -3).' '.substr($postcode, -3);
        }

        $this->merge(['postcode' => $postcode]);
    }

    public function messages(): array
    {
        return [
            'nation.in' => 'The nation must be either england-wales or scotland.',
            'postcode.regex' => 'The postcode must be a complete UK postcode.',
        ];
    }
}
