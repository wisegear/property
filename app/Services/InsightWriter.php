<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class InsightWriter
{
    public function priceSpike(object|array $row): string
    {
        $postcode = $this->value($row, 'postcode', 'area_code');
        $growth = $this->value($row, 'growth');
        $sales = $this->value($row, 'sales');
        $periodLabel = $this->value($row, 'period_label');

        return "Median property prices in {$postcode} rose {$growth}% in {$periodLabel} based on {$sales} recorded sales.";
    }

    public function demandCollapse(object|array $row): string
    {
        $postcode = $this->value($row, 'postcode', 'area_code');
        $salesChange = $this->value($row, 'sales_change');
        $sales = $this->value($row, 'sales');
        $periodLabel = $this->value($row, 'period_label');

        return "Property transactions in {$postcode} fell {$salesChange}% in {$periodLabel} based on {$sales} recorded sales.";
    }

    public function priceCollapse(object|array $row): string
    {
        $sector = $this->value($row, 'sector', 'postcode', 'area_code');
        $growth = $this->value($row, 'growth');
        $previousPrice = $this->value($row, 'previous_price');
        $currentPrice = $this->value($row, 'current_price');

        return "Median property prices in postcode sector {$sector} fell {$growth}% over the last 12 months. Previous period median price: £{$previousPrice}. Current period median price: £{$currentPrice}.";
    }

    public function liquiditySurge(object|array $row): string
    {
        $sector = $this->value($row, 'sector', 'postcode', 'area_code');
        $salesChange = $this->value($row, 'sales_change');

        return "Property transactions in postcode sector {$sector} increased {$salesChange}% over the past 12 months compared with the previous year.";
    }

    public function liquidityStress(object|array $row): string
    {
        $sector = $this->value($row, 'sector', 'postcode', 'area_code');
        $salesChange = $this->value($row, 'sales_change');
        $priceGrowth = $this->value($row, 'price_growth');
        $periodLabel = $this->value($row, 'period_label');

        return "Property transactions in postcode sector {$sector} fell {$salesChange}% in {$periodLabel} while median prices still rose {$priceGrowth}%, suggesting weakening market liquidity.";
    }

    public function marketFreeze(object|array $row): string
    {
        $sector = $this->value($row, 'sector', 'postcode', 'area_code');
        $salesChange = $this->value($row, 'sales_change');

        return "Property transactions in postcode sector {$sector} fell {$salesChange}% over the past 12 months, indicating a sharp slowdown in market activity.";
    }

    public function sectorOutperformance(object|array $row): string
    {
        $sector = $this->value($row, 'sector', 'area_code');
        $sectorGrowth = $this->value($row, 'sector_growth');
        $ukGrowth = $this->value($row, 'uk_growth');
        $sales = $this->value($row, 'sales');
        $periodLabel = $this->value($row, 'period_label');

        return "Median property prices in {$sector} rose {$sectorGrowth}% in {$periodLabel} versus {$ukGrowth}% nationally based on {$sales} recorded sales.";
    }

    public function momentumReversal(object|array $row): string
    {
        $sector = $this->value($row, 'sector', 'area_code');
        $sales = $this->value($row, 'sales');
        $currentPeriodLabel = $this->value($row, 'current_period_label', 'period_label');
        $previousPeriodLabel = $this->value($row, 'previous_period_label');

        return "Median property prices in {$sector} rose strongly in {$previousPeriodLabel} but fell in {$currentPeriodLabel}, indicating a possible reversal in local price momentum based on {$sales} recorded sales.";
    }

    public function unexpectedHotspot(object|array $row): string
    {
        $sector = $this->value($row, 'sector', 'postcode', 'area_code');
        $sectorGrowth = $this->value($row, 'sector_growth');
        $ukGrowth = $this->value($row, 'uk_growth');

        return "Median property prices in postcode sector {$sector} rose {$sectorGrowth}% over the past 12 months, significantly outperforming the UK average increase of {$ukGrowth}%. Despite this surge, the sector's median price remains below the national average.";
    }

    public function generateWithAI(array $data): string
    {
        $apiKey = (string) config('services.openai.key');
        if ($apiKey === '') {
            return $this->fallbackFromData($data);
        }

        $payload = $this->promptPayload($data);

        try {
            $response = Http::timeout(15)
                ->withToken($apiKey)
                ->post(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/').'/responses', [
                    'model' => config('services.openai.model', 'gpt-5-nano'),
                    'instructions' => 'Write exactly 1 short sentence in a factual tone. No speculation. No fluff.',
                    'input' => $payload,
                    'max_output_tokens' => 60,
                ])
                ->throw()
                ->json();

            $text = $this->extractResponseText(is_array($response) ? $response : []);
            if ($text === '') {
                return $this->fallbackFromData($data);
            }

            return $this->normaliseSentence($text);
        } catch (Throwable) {
            return $this->fallbackFromData($data);
        }
    }

    protected function value(object|array $row, string ...$keys): string
    {
        foreach ($keys as $key) {
            if (is_array($row) && array_key_exists($key, $row)) {
                return (string) $row[$key];
            }

            if (is_object($row) && isset($row->{$key})) {
                return (string) $row->{$key};
            }
        }

        return '';
    }

    protected function promptPayload(array $data): string
    {
        $lines = [
            'Generate a one-sentence market insight from this anomaly data:',
            'Area: '.$this->arrayValue($data, 'area', 'postcode', 'area_code'),
            'Metric: '.$this->arrayValue($data, 'metric', 'insight_type'),
            'Change: '.$this->arrayValue($data, 'change', 'growth', 'sales_change'),
            'Transactions: '.$this->arrayValue($data, 'transactions', 'sales'),
            'Period: '.$this->arrayValue($data, 'period', 'period_label', 'period'),
        ];

        return implode("\n", array_filter($lines, fn (string $line) => ! str_ends_with($line, ': ')));
    }

    protected function fallbackFromData(array $data): string
    {
        $insightType = strtolower(trim((string) ($data['insight_type'] ?? '')));
        $metric = strtolower(trim((string) ($data['metric'] ?? '')));

        if ($insightType === 'liquidity_surge') {
            return $this->liquiditySurge([
                'area_code' => $this->arrayValue($data, 'area', 'postcode', 'area_code'),
                'sales_change' => $this->normaliseChange($this->arrayValue($data, 'change', 'sales_change')),
            ]);
        }

        if ($insightType === 'liquidity_stress') {
            return $this->liquidityStress([
                'area_code' => $this->arrayValue($data, 'area', 'postcode', 'area_code'),
                'sales_change' => $this->normaliseChange($this->arrayValue($data, 'change', 'sales_change')),
                'price_growth' => $this->normaliseChange($this->arrayValue($data, 'benchmark', 'price_growth')),
                'period_label' => $this->arrayValue($data, 'period', 'period_label'),
            ]);
        }

        if ($insightType === 'market_freeze') {
            return $this->marketFreeze([
                'area_code' => $this->arrayValue($data, 'area', 'postcode', 'area_code'),
                'sales_change' => $this->normaliseChange($this->arrayValue($data, 'change', 'sales_change')),
            ]);
        }

        if ($insightType === 'demand_collapse' || str_contains($metric, 'sales') || str_contains($metric, 'transaction')) {
            return $this->demandCollapse([
                'area_code' => $this->arrayValue($data, 'area', 'postcode', 'area_code'),
                'sales_change' => $this->normaliseChange($this->arrayValue($data, 'change', 'sales_change')),
                'sales' => $this->arrayValue($data, 'transactions', 'sales'),
                'period_label' => $this->arrayValue($data, 'period', 'period_label'),
            ]);
        }

        if ($insightType === 'price_collapse') {
            return $this->priceCollapse([
                'area_code' => $this->arrayValue($data, 'area', 'postcode', 'area_code'),
                'growth' => $this->normaliseChange($this->arrayValue($data, 'change', 'growth')),
                'previous_price' => $this->arrayValue($data, 'previous_price'),
                'current_price' => $this->arrayValue($data, 'current_price'),
            ]);
        }

        if ($insightType === 'sector_outperformance' || str_contains($metric, 'outperformance')) {
            return $this->sectorOutperformance([
                'area_code' => $this->arrayValue($data, 'area', 'postcode', 'area_code'),
                'sector_growth' => $this->normaliseChange($this->arrayValue($data, 'change', 'sector_growth')),
                'uk_growth' => $this->normaliseChange($this->arrayValue($data, 'benchmark', 'uk_growth')),
                'sales' => $this->arrayValue($data, 'transactions', 'sales'),
                'period_label' => $this->arrayValue($data, 'period', 'period_label'),
            ]);
        }

        if ($insightType === 'momentum_reversal' || str_contains($metric, 'momentum')) {
            return $this->momentumReversal([
                'area_code' => $this->arrayValue($data, 'area', 'postcode', 'area_code'),
                'sales' => $this->arrayValue($data, 'transactions', 'sales'),
                'current_period_label' => $this->arrayValue($data, 'period', 'period_label'),
                'previous_period_label' => $this->arrayValue($data, 'previous_period_label'),
            ]);
        }

        if ($insightType === 'unexpected_hotspot') {
            return $this->unexpectedHotspot([
                'area_code' => $this->arrayValue($data, 'area', 'postcode', 'area_code'),
                'sector_growth' => $this->normaliseChange($this->arrayValue($data, 'change', 'sector_growth')),
                'uk_growth' => $this->normaliseChange($this->arrayValue($data, 'benchmark', 'uk_growth')),
            ]);
        }

        if ($insightType === 'price_spike' || str_contains($metric, 'price')) {
            return $this->priceSpike([
                'area_code' => $this->arrayValue($data, 'area', 'postcode', 'area_code'),
                'growth' => $this->normaliseChange($this->arrayValue($data, 'change', 'growth')),
                'sales' => $this->arrayValue($data, 'transactions', 'sales'),
                'period_label' => $this->arrayValue($data, 'period', 'period_label'),
            ]);
        }

        $area = $this->arrayValue($data, 'area', 'postcode', 'area_code');
        $metricLabel = $this->arrayValue($data, 'metric', 'insight_type');
        $change = $this->arrayValue($data, 'change', 'growth', 'sales_change');
        $transactions = $this->arrayValue($data, 'transactions', 'sales');
        $period = $this->arrayValue($data, 'period', 'period_label');

        return $this->normaliseSentence("{$metricLabel} in {$area} changed {$change} during {$period} based on {$transactions} recorded transactions.");
    }

    protected function arrayValue(array $data, string ...$keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                return (string) $data[$key];
            }
        }

        return '';
    }

    protected function normaliseChange(string $value): string
    {
        return rtrim(ltrim(trim($value), '+'), '%');
    }

    protected function extractResponseText(array $response): string
    {
        $outputText = trim((string) ($response['output_text'] ?? ''));
        if ($outputText !== '') {
            return $outputText;
        }

        foreach (($response['output'] ?? []) as $item) {
            foreach (($item['content'] ?? []) as $content) {
                if (($content['type'] ?? null) === 'output_text') {
                    return trim((string) ($content['text'] ?? ''));
                }
            }
        }

        return '';
    }

    protected function normaliseSentence(string $text): string
    {
        $sentence = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($sentence === '') {
            return '';
        }

        $parts = preg_split('/(?<=[.!?])\s+/', $sentence);
        $firstSentence = trim((string) ($parts[0] ?? $sentence));

        return rtrim($firstSentence, " \t\n\r\0\x0B.!?").'.';
    }
}
