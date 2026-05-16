<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MortgageCalcRequest extends FormRequest
{
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
        if (! $this->isMethod('post')) {
            return [];
        }

        return [
            'amount' => ['required', 'string'],
            'term' => ['required', 'integer', 'min:1', 'max:50'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'annual_overpayment' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Please enter a mortgage amount.',
            'term.required' => 'Please enter a mortgage term.',
            'term.integer' => 'The mortgage term must be a whole number of years.',
            'term.min' => 'The mortgage term must be at least 1 year.',
            'term.max' => 'The mortgage term cannot be more than 50 years.',
            'rate.required' => 'Please enter an interest rate.',
            'rate.numeric' => 'The interest rate must be a number.',
            'rate.min' => 'The interest rate cannot be negative.',
            'rate.max' => 'The interest rate cannot be more than 100%.',
        ];
    }

    /**
     * @return array<int, Closure(): void>
     */
    public function after(): array
    {
        return [
            function (): void {
                if (! $this->isMethod('post')) {
                    return;
                }

                if ($this->parseCurrency($this->input('amount')) <= 0) {
                    $this->validator->errors()->add('amount', 'Please enter a valid mortgage amount.');
                }

                $annualOverpayment = $this->parseCurrency($this->input('annual_overpayment'));
                if ($annualOverpayment === null) {
                    return;
                }

                if ($annualOverpayment === -1.0) {
                    $this->validator->errors()->add('annual_overpayment', 'Please enter a valid annual overpayment amount.');
                } elseif ($annualOverpayment < 0) {
                    $this->validator->errors()->add('annual_overpayment', 'Annual overpayment cannot be negative.');
                }
            },
        ];
    }

    public function mortgageAmount(): int
    {
        return (int) round($this->parseCurrency($this->input('amount')) ?? 0);
    }

    public function annualOverpayment(): int
    {
        return (int) round($this->parseCurrency($this->input('annual_overpayment')) ?? 0);
    }

    public function annualOverpaymentRaw(): string
    {
        return trim((string) $this->input('annual_overpayment', ''));
    }

    private function parseCurrency(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = str_replace([',', ' '], '', trim((string) $value));
        if ($normalized === '') {
            return null;
        }

        if (! is_numeric($normalized)) {
            return -1;
        }

        return (float) $normalized;
    }
}
