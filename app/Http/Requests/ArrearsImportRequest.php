<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArrearsImportRequest extends FormRequest
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
            'csv_file.required' => 'Upload a CSV file to import arrears data.',
            'csv_file.mimes' => 'The arrears import must be a CSV or TXT file.',
        ];
    }
}
