@extends('layouts.app')
@include('partials.chartjs-head')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-10 md:py-12">
    {{-- Hero / summary card --}}
    <section class="relative z-0 overflow-hidden rounded-lg border border-gray-200 bg-white/80 p-6 md:p-8 shadow-sm mb-8 flex flex-col md:flex-row justify-between items-center">
        @include('partials.hero-background')
        <div class="max-w-4xl">
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-900">Mortgage Calculator</h1>
            <p class="mt-2 text-sm leading-6 text-gray-700">
                <span class="text-zinc-600">Calculate mortgage payments for repayment and interest only mortgages.  Select term, interest rate and get information that factors
                    in a lender stress rate to see the potential impact on payments.</span><br>
                </span>
            </p>
        </div>
        <div class="mt-6 md:mt-0 md:ml-8 flex-shrink-0">
            <img src="{{ asset('assets/images/site/calculator.jpg') }}" alt="Mortgage-Caclulator" class="w-72 h-auto">
        </div>
</section>    

    {{-- Calculator form panel --}}
    <section class="rounded-lg border border-gray-200 bg-white/80 p-6 md:p-8 shadow-sm mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Calculate Your Mortgage</h2>
        <form method="POST" action="{{ route('mortgagecalc.index') }}" class="space-y-4">
            @csrf

            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <label for="amount" class="block text-sm font-medium text-gray-700">Mortgage Amount</label>
                    <input type="text" name="amount" id="amount" value="{{ $input['amount'] ?? old('amount') }}" placeholder="e.g. 250,000" class="p-2 mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:border-lime-500 focus:ring-lime-500 sm:text-sm" required>
                    <x-input-error class="mt-2" :messages="$errors->get('amount')" />
                </div>

                <div class="flex-1">
                    <label for="term" class="block text-sm font-medium text-gray-700">Term (years)</label>
                    <input type="number" name="term" id="term" value="{{ $input['term'] ?? old('term') }}" placeholder="e.g. 25" min="1" class="p-2 mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:border-lime-500 focus:ring-lime-500 sm:text-sm" required>
                    <x-input-error class="mt-2" :messages="$errors->get('term')" />
                </div>

                <div class="flex-1">
                    <label for="rate" class="block text-sm font-medium text-gray-700">Interest Rate (%)</label>
                    <input type="number" name="rate" id="rate" value="{{ $input['rate'] ?? old('rate') }}" placeholder="e.g. 5.5" step="0.01" min="0" class="p-2 mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:border-lime-500 focus:ring-lime-500 sm:text-sm" required>
                    <x-input-error class="mt-2" :messages="$errors->get('rate')" />
                </div>

                <div class="flex-1">
                    <label for="annual_overpayment" class="block text-sm font-medium text-gray-700">Annual overpayment (Optional)</label>
                    <input type="text" name="annual_overpayment" id="annual_overpayment" value="{{ $input['annual_overpayment'] ?? old('annual_overpayment') }}" placeholder="e.g. 2,500" class="p-2 mt-1 block w-full rounded-md border border-gray-300 shadow-sm focus:border-lime-500 focus:ring-lime-500 sm:text-sm">
                    <x-input-error class="mt-2" :messages="$errors->get('annual_overpayment')" />
                </div>
            </div>

            <div>
                <button type="submit" class="inner-button">
                    Calculate
                </button>
            </div>
        </form>
    </section>

    <!-- Form results -->
    <div class="">
@if(!empty($result))
<section class="rounded-lg border border-gray-200 bg-white/80 p-6 md:p-8 shadow-sm">
  <h3 class="text-lg font-semibold text-gray-900">Results</h3>
  <p class="text-sm text-zinc-500 mb-6">The results below show payment for both a repayment and interest only mortgage with charts demonstrating how the mortgage is paid off,
    or not if interest only.  Many mortgage lenders still use a stress rate in their affordability calculations, the bottom panel shows what the impact would be if a stress rate of
    <span class="text-rose-500">{{ rtrim(rtrim(number_format($result['stress_rate'], 2), '0'), '.') }}%</span> was added to the rate you entered of 
    <span class="text-rose-500">{{ rtrim(rtrim(number_format($result['rate_pct'], 2), '0'), '.') }}%</span>.
  </p>

  {{-- Input summary --}}
  <div class="grid sm:grid-cols-3 gap-4 text-sm mb-6 border rounded-lg p-4 text-center">
    <div class="flex flex-col items-center">
      <div class="text-gray-500">Amount</div>
      <div class="font-medium">£{{ number_format($result['amount']) }}</div>
    </div>
    <div class="flex flex-col items-center">
      <div class="text-gray-500">Term</div>
      <div class="font-medium">{{ $result['term_years'] }} years</div>
    </div>
    <div class="flex flex-col items-center">
      <div class="text-gray-500">Interest rate</div>
      <div class="font-medium">{{ rtrim(rtrim(number_format($result['rate_pct'], 2), '0'), '.') }}%</div>
    </div>
  </div>

  <div class="grid md:grid-cols-2 gap-6">
    {{-- Repayment panel --}}
    <div class="rounded-lg border border-gray-200 bg-white p-5">
      <h4 class="text-base font-semibold text-gray-900 mb-3">Repayment Mortgage</h4>
      <dl class="grid grid-cols-3 gap-3 text-sm">
        <div>
          <dt class="text-gray-500">Monthly payment</dt>
          <dd class="font-medium">£{{ number_format($result['repayment_monthly'], 2) }}</dd>
        </div>
        <div>
          <dt class="text-gray-500">Annual payment</dt>
          <dd class="font-medium">£{{ number_format($result['repayment_annual'], 2) }}</dd>
        </div>
        <div>
          <dt class="text-gray-500">Total amount paid</dt>
          <dd class="font-medium">£{{ number_format($result['repayment_total_paid'], 2) }}</dd>
        </div>
      </dl>
      <p class="mt-3 text-xs text-gray-500">
        On a repayment basis the monthly payment pays the interest due and a portion of the capital borrowed.  The amount of capital reduces slowly at the start, eventually more
        capital is paid each month than interest.  Using a repayment mortgage and assuming all payments are made the full amount is repaid at the end of term.
      </p>
      <p class="mt-3 text-xs text-gray-500">For every <span class="text-rose-700">£1</span>  borrowed you repay <span class="text-rose-700">£{{ number_format($result['repayment_per_pound'], 2) }}</span> over the term.</p>
      <div class="mt-5">
        <h5 class="text-sm font-medium text-gray-700 mb-2">Balance over term</h5>
        <div class="relative h-72">
          <canvas id="repaymentChart" class="absolute inset-0 w-full h-full"></canvas>
        </div>
        <p class="mt-2 text-xs text-gray-500">
          Shows outstanding balance decreasing to £0 by the end of the term.
          @if(!empty($result['overpayment_impact']))
            The annual overpayment path is overlaid for comparison.
          @endif
        </p>
      </div>
    </div>

    {{-- Interest-only panel --}}
    <div class="rounded-lg border border-gray-200 bg-white p-5">
      <h4 class="text-base font-semibold text-gray-900 mb-3">Interest-Only Mortgage</h4>
      <dl class="grid grid-cols-3 gap-3 text-sm">
        <div>
          <dt class="text-gray-500">Monthly interest</dt>
          <dd class="font-medium">£{{ number_format($result['interest_only_monthly'], 2) }}</dd>
        </div>
        <div>
          <dt class="text-gray-500">Annual interest</dt>
          <dd class="font-medium">£{{ number_format($result['interest_only_annual'], 2) }}</dd>
        </div>
        <div>
          <dt class="text-gray-500">Total interest paid</dt>
          <dd class="font-medium">£{{ number_format($result['interest_only_total_interest'], 2) }}</dd>
        </div>
      </dl>
      <p class="mt-3 text-xs text-gray-500">
        With interest only the payments cover the interest only, the capital is not reduced, throughout the term £{{ number_format($result['amount']) }} is always owed, unless additional
        payments are made throughout the term.  Lenders will expect proof of ability to repay the loan at the end of the term.  This could be through selling the property or using an
        acceptable asset at or before the term ends.
      </p>
      <p class="mt-3 text-xs text-gray-500">For every <span class="text-rose-700">£1</span> borrowed you pay <span class="text-rose-700">£{{ number_format($result['interest_only_per_pound'], 2) }}</span> over the term.</p>
      <div class="mt-5">
        <h5 class="text-sm font-medium text-gray-700 mb-2">Balance over term</h5>
        <div class="relative h-72">
          <canvas id="interestOnlyChart" class="absolute inset-0 w-full h-full"></canvas>
        </div>
        <p class="mt-2 text-xs text-gray-500">
          Shows outstanding balance remaining constant at the original loan amount.
          @if(!empty($result['overpayment_impact']))
            The annual overpayment path is overlaid to show how the balance would reduce if you made extra capital payments.
          @endif
        </p>
      </div>
    </div>
  </div>
  @if(!empty($result['overpayment_impact']))
  <div class="mt-6 rounded-lg border border-lime-300 bg-lime-50/60 p-5">
    <h4 class="text-base font-semibold text-gray-900 mb-2">Annual Overpayment Impact</h4>
    <p class="text-sm text-zinc-700">
      This compares repayment and interest-only schedules with a yearly overpayment of
      <span class="font-semibold">£{{ number_format($result['overpayment_impact']['annual_overpayment']) }}</span>.
    </p>
    <h5 class="mt-4 text-sm font-medium text-gray-700">Repayment Mortgage</h5>
    <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4 text-sm">
      <div class="rounded-lg border border-lime-200 bg-white p-4">
        <div class="text-gray-500">Time saved</div>
        <div class="mt-1 font-medium">{{ $result['overpayment_impact']['repayment']['time_saved_label'] }}</div>
      </div>
      <div class="rounded-lg border border-lime-200 bg-white p-4">
        <div class="text-gray-500">Interest saved</div>
        <div class="mt-1 font-medium">£{{ number_format($result['overpayment_impact']['repayment']['interest_saved'], 2) }}</div>
      </div>
      <div class="rounded-lg border border-lime-200 bg-white p-4">
        <div class="text-gray-500">New estimated mortgage length</div>
        <div class="mt-1 font-medium">{{ $result['overpayment_impact']['repayment']['new_term_label'] }}</div>
      </div>
      <div class="rounded-lg border border-lime-200 bg-white p-4">
        <div class="text-gray-500">Total interest with overpayments</div>
        <div class="mt-1 font-medium">£{{ number_format($result['overpayment_impact']['repayment']['total_interest'], 2) }}</div>
      </div>
    </div>

    <h5 class="mt-5 text-sm font-medium text-gray-700">Interest-Only Mortgage</h5>
    <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4 text-sm">
      <div class="rounded-lg border border-lime-200 bg-white p-4">
        <div class="text-gray-500">Time saved</div>
        <div class="mt-1 font-medium">{{ $result['overpayment_impact']['interest_only']['time_saved_label'] }}</div>
      </div>
      <div class="rounded-lg border border-lime-200 bg-white p-4">
        <div class="text-gray-500">Interest saved</div>
        <div class="mt-1 font-medium">£{{ number_format($result['overpayment_impact']['interest_only']['interest_saved'], 2) }}</div>
      </div>
      <div class="rounded-lg border border-lime-200 bg-white p-4">
        <div class="text-gray-500">New estimated mortgage length</div>
        <div class="mt-1 font-medium">{{ $result['overpayment_impact']['interest_only']['new_term_label'] }}</div>
      </div>
      <div class="rounded-lg border border-lime-200 bg-white p-4">
        <div class="text-gray-500">Total interest with overpayments</div>
        <div class="mt-1 font-medium">£{{ number_format($result['overpayment_impact']['interest_only']['total_interest'], 2) }}</div>
      </div>
    </div>
  </div>
  @endif
  <!-- Stress rate impact -->
  <div class="text-smmt-8 rounded-lg border border-rose-600 p-5 mt-6">
    <h4 class="text-base font-semibold text-rose-600 mb-2">Stress Rate Impact</h4>
    <p class="text-sm text-zinc-700">
      Some lenders assess affordability by <em>stressing</em> the interest rate. Using a stress rate of
      <span class="font-semibold">{{ rtrim(rtrim(number_format($result['stress_rate'], 2), '0'), '.') }}%</span> on top of your entered rate of
      <span class="font-semibold">{{ rtrim(rtrim(number_format($result['rate_pct'], 2), '0'), '.') }}%</span> (total <span class="font-semibold">{{ rtrim(rtrim(number_format($result['stressed_rate_pct'], 2), '0'), '.') }}%</span>).
      This is not the rate you will pay.  Lenders are demonstrating that if the rate was to increase, a borrower could still afford the payments.
    </p>
    <ul class="mt-3 text-sm text-zinc-700 space-y-1">
      <li>
        • <span class="font-medium">Repayment</span>: you would pay an extra
        <span class="font-semibold">£{{ number_format($result['repayment_monthly_extra'], 2) }}</span> each month, increasing the monthly payment to
        <span class="font-semibold">£{{ number_format($result['repayment_monthly_stressed'], 2) }}</span>.
      </li>
      <li>
        • <span class="font-medium">Interest-only</span>: you would pay an extra
        <span class="font-semibold">£{{ number_format($result['interest_only_monthly_extra'], 2) }}</span> each month, increasing the monthly payment to
        <span class="font-semibold">£{{ number_format($result['interest_only_monthly_stressed'], 2) }}</span>.
      </li>
    </ul>
  </div>
</section>
@endif
    </div>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const currencyInputIds = ['amount', 'annual_overpayment'];

    const formatCurrencyInput = (input) => {
      const digitsOnly = input.value.replace(/[^\d]/g, '');
      input.value = digitsOnly === ''
        ? ''
        : new Intl.NumberFormat('en-GB', { maximumFractionDigits: 0 }).format(Number(digitsOnly));
    };

    currencyInputIds.forEach((inputId) => {
      const input = document.getElementById(inputId);
      if (! input) {
        return;
      }

      formatCurrencyInput(input);

      input.addEventListener('input', function () {
        formatCurrencyInput(input);
      });

      input.addEventListener('blur', function () {
        formatCurrencyInput(input);
      });
    });

    const mortgageForm = document.querySelector('form[action="{{ route('mortgagecalc.index') }}"]');
    if (mortgageForm) {
      mortgageForm.addEventListener('submit', function () {
        currencyInputIds.forEach((inputId) => {
          const input = document.getElementById(inputId);
          if (input) {
            formatCurrencyInput(input);
          }
        });
      });
    }
  });
</script>
@if(!empty($result))
<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.Chart === 'undefined') {
      console.warn('Chart.js not found: make sure it is included in your layout.');
      return;
    }

    const amount = {{ (int) $result['amount'] }};
    const repaymentPoints = @json($result['repayment_chart']['standard_points']);
    const interestOnlyPoints = @json($result['interest_only_chart']['standard_points']);
    const interestOnlyOverpaymentPoints = @json($result['interest_only_chart']['overpayment_points']);
    const overpaymentPoints = @json($result['repayment_chart']['overpayment_points']);

    const fmt = (value) => '£' + new Intl.NumberFormat('en-GB', { maximumFractionDigits: 0 }).format(value);
    const yearLabel = (value) => value === 0 ? 'Start' : `${value} year${value === 1 ? '' : 's'}`;
    const buildChartMax = (value) => {
      const paddedValue = Math.max(value * 1.1, value + 10000);
      const preferredSteps = [10000, 25000, 50000, 100000, 200000];
      const step = preferredSteps.find((candidate) => paddedValue <= candidate * 10) ?? 100000;

      return Math.ceil(paddedValue / step) * step;
    };
    const buildCurrencyTicks = (maxValue) => {
      const preferredSteps = [5000, 10000, 20000, 25000, 50000, 100000, 200000];
      const roughStep = maxValue / 8;
      const step = preferredSteps.find((value) => roughStep <= value) ?? Math.ceil(roughStep / 100000) * 100000;
      const ticks = [];

      for (let value = 0; value < maxValue; value += step) {
        ticks.push({ value });
      }

      if (ticks.length === 0 || ticks[ticks.length - 1].value !== maxValue) {
        ticks.push({ value: maxValue });
      }

      return ticks;
    };
    const chartMax = buildChartMax(amount);

    const lineOptions = {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'nearest', intersect: false },
      layout: {
        padding: {
          top: 8,
          right: 12,
          bottom: 0,
          left: 0,
        }
      },
      scales: {
        x: {
          type: 'linear',
          title: {
            display: true,
            text: 'Years'
          },
          ticks: {
            callback: (value) => yearLabel(value)
          }
        },
        y: {
          min: 0,
          max: chartMax,
          afterBuildTicks: (axis) => {
            axis.ticks = buildCurrencyTicks(chartMax);
          },
          ticks: {
            autoSkip: false,
            callback: (value) => fmt(value)
          }
        }
      },
      plugins: {
        tooltip: {
          callbacks: {
            title: (items) => yearLabel(items[0].parsed.x),
            label: (ctx) => `${ctx.dataset.label}: ${fmt(ctx.parsed.y)}`
          }
        }
      }
    };

    const repCtx = document.getElementById('repaymentChart');
    if (repCtx) {
      const repaymentDatasets = [
        {
          label: overpaymentPoints ? 'Standard repayment' : 'Outstanding balance',
          data: repaymentPoints,
          borderColor: overpaymentPoints ? '#52525b' : '#65a30d',
          backgroundColor: overpaymentPoints ? '#52525b' : '#65a30d',
          tension: 0.15,
          pointRadius: 2,
          borderWidth: 2,
        }
      ];

      if (overpaymentPoints) {
        repaymentDatasets.push({
          label: 'With annual overpayments',
          data: overpaymentPoints,
          borderColor: '#65a30d',
          backgroundColor: '#65a30d',
          tension: 0.15,
          pointRadius: 2,
          borderWidth: 3,
        });
      }

      new Chart(repCtx, {
        type: 'line',
        data: {
          datasets: repaymentDatasets
        },
        options: {
          ...lineOptions,
          plugins: {
            ...lineOptions.plugins,
            legend: { display: !! overpaymentPoints }
          }
        },
      });
    }

    const ioCtx = document.getElementById('interestOnlyChart');
    if (ioCtx) {
      const interestOnlyDatasets = [
        {
          label: interestOnlyOverpaymentPoints ? 'Standard interest-only' : 'Outstanding balance',
          data: interestOnlyPoints,
          borderColor: interestOnlyOverpaymentPoints ? '#52525b' : '#65a30d',
          backgroundColor: interestOnlyOverpaymentPoints ? '#52525b' : '#65a30d',
          tension: 0.15,
          pointRadius: 2,
          borderWidth: 2,
        }
      ];

      if (interestOnlyOverpaymentPoints) {
        interestOnlyDatasets.push({
          label: 'Interest-only with annual overpayments',
          data: interestOnlyOverpaymentPoints,
          borderColor: '#65a30d',
          backgroundColor: '#65a30d',
          tension: 0.15,
          pointRadius: 2,
          borderWidth: 3,
        });
      }

      new Chart(ioCtx, {
        type: 'line',
        data: {
          datasets: interestOnlyDatasets
        },
        options: {
          ...lineOptions,
          plugins: {
            ...lineOptions.plugins,
            legend: { display: !! interestOnlyOverpaymentPoints }
          }
        },
      });
    }
  });
</script>
@endif
</div>
@endsection
