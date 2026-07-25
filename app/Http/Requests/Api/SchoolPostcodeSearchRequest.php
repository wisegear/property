<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SchoolPostcodeSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'postcode' => [
                'bail',
                'required',
                'string',
                'regex:/^(?:GIR 0AA|(?!(?:AB|BT|DD|DG|EH|FK|G|GY|HS|IM|IV|JE|KA|KW|KY|ML|PA|PH|TD|ZE)(?=[0-9]))[A-Z]{1,2}[0-9][0-9A-Z]? [0-9][A-Z]{2})$/',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'postcode.regex' => 'The postcode field must be a complete England or Wales postcode.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $postcode = strtoupper((string) $this->query('postcode', ''));
        $postcode = preg_replace('/\s+/', '', $postcode) ?? '';

        if (strlen($postcode) >= 5) {
            $postcode = substr($postcode, 0, -3).' '.substr($postcode, -3);
        }

        $this->merge([
            'postcode' => $postcode,
        ]);
    }
}
