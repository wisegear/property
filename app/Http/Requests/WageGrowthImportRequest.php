<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WageGrowthImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'csv_file.required' => 'Upload a CSV file to import wage growth data.',
            'csv_file.mimes' => 'The wage growth import must be a CSV or TXT file.',
        ];
    }
}
