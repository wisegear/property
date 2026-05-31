<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnemploymentImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'csv_file.required' => 'Upload a CSV file to import unemployment data.',
            'csv_file.mimes' => 'The unemployment import must be a CSV or TXT file.',
        ];
    }
}
