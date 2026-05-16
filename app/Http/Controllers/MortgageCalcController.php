<?php

namespace App\Http\Controllers;

use App\Http\Requests\MortgageCalcRequest;
use App\Services\FormAnalytics;
use App\Services\MortgageCalculatorService;
use Illuminate\Contracts\View\View;

class MortgageCalcController extends Controller
{
    public function __construct(
        private MortgageCalculatorService $mortgageCalculatorService
    ) {}

    public function index(MortgageCalcRequest $request): View
    {
        if (! $request->isMethod('post')) {
            return view('mortgagecalc.index');
        }

        $validated = $request->validated();
        $amountRaw = (string) $validated['amount'];
        $termYears = (int) $validated['term'];
        $ratePct = (float) $validated['rate'];
        $annualOverpaymentRaw = $request->annualOverpaymentRaw();
        $annualOverpayment = $request->annualOverpayment();

        FormAnalytics::record('mortgage_calculator', [
            'amount' => $request->mortgageAmount(),
            'term_years' => $termYears,
            'interest_rate' => $ratePct,
            'annual_overpayment' => $annualOverpayment,
        ]);

        return view('mortgagecalc.index', [
            'result' => $this->mortgageCalculatorService->calculate(
                $request->mortgageAmount(),
                $termYears,
                $ratePct,
                $annualOverpayment
            ),
            'input' => [
                'amount' => $amountRaw,
                'term' => $termYears,
                'rate' => $ratePct,
                'annual_overpayment' => $annualOverpaymentRaw,
            ],
        ]);
    }
}
