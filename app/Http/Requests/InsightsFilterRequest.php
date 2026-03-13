<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InsightsFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'string', Rule::in([
                'price_spike',
                'price_collapse',
                'demand_collapse',
                'liquidity_stress',
                'liquidity_surge',
                'market_freeze',
                'sector_outperformance',
                'momentum_reversal',
                'unexpected_hotspot',
            ])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.max' => 'Search terms must be 120 characters or fewer.',
            'type.in' => 'Select a valid insight type filter.',
        ];
    }
}
