<?php

namespace Tests\Feature;

use Tests\TestCase;

class MortgageCalculatorTest extends TestCase
{
    public function test_blank_overpayment_preserves_existing_behaviour(): void
    {
        $response = $this->post('/mortgage-calculator', [
            'amount' => '250,000',
            'term' => 30,
            'rate' => '4.5',
            'annual_overpayment' => '',
        ]);

        $response->assertOk();
        $response->assertViewHas('result', function (array $result): bool {
            return $result['annual_overpayment'] === 0
                && $result['overpayment_impact'] === null
                && round($result['repayment_monthly'], 2) === 1266.71;
        });
    }

    public function test_zero_overpayment_does_not_create_impact_panel_data(): void
    {
        $response = $this->post('/mortgage-calculator', [
            'amount' => '250,000',
            'term' => 30,
            'rate' => '4.5',
            'annual_overpayment' => '0',
        ]);

        $response->assertOk();
        $response->assertViewHas('result', function (array $result): bool {
            return $result['annual_overpayment'] === 0
                && $result['overpayment_impact'] === null;
        });
    }

    public function test_repayment_overpayment_reduces_term_and_interest(): void
    {
        $response = $this->post('/mortgage-calculator', [
            'amount' => '250,000',
            'term' => 30,
            'rate' => '4.5',
            'annual_overpayment' => '2,500',
        ]);

        $response->assertOk();
        $response->assertViewHas('result', function (array $result): bool {
            $impact = $result['overpayment_impact'];

            return is_array($impact)
                && $impact['repayment']['months_saved'] > 0
                && $impact['repayment']['interest_saved'] > 0
                && $impact['repayment']['new_term_months'] < ($result['term_years'] * 12)
                && $impact['interest_only']['interest_saved'] > 0
                && count($result['repayment_chart']['overpayment_points'] ?? []) > 1
                && count($result['interest_only_chart']['overpayment_points'] ?? []) > 1;
        });
    }

    public function test_large_overpayment_does_not_break_calculation(): void
    {
        $response = $this->post('/mortgage-calculator', [
            'amount' => '10,000',
            'term' => 2,
            'rate' => '5',
            'annual_overpayment' => '50,000',
        ]);

        $response->assertOk();
        $response->assertViewHas('result', function (array $result): bool {
            $impact = $result['overpayment_impact'];
            $points = $result['repayment_chart']['overpayment_points'] ?? [];
            $lastPoint = end($points);

            return is_array($impact)
                && $impact['repayment']['new_term_months'] <= 12
                && $impact['repayment']['total_interest'] >= 0
                && $impact['interest_only']['total_interest'] >= 0
                && is_array($lastPoint)
                && $lastPoint['y'] === 0.0;
        });
    }

    public function test_form_preserves_overpayment_input_after_submit(): void
    {
        $response = $this->post('/mortgage-calculator', [
            'amount' => '250,000',
            'term' => 30,
            'rate' => '4.5',
            'annual_overpayment' => '2,500',
        ]);

        $response->assertOk();
        $response->assertViewHas('input', function (array $input): bool {
            return $input['annual_overpayment'] === '2,500';
        });
        $response->assertSee('Annual Overpayment Impact');
        $response->assertSee('Repayment Mortgage');
        $response->assertSee('Interest-Only Mortgage');
        $response->assertDontSee('Balance with annual overpayments');
    }
}
