<?php

if (! function_exists('marketColor')) {
    function marketColor(float|int $value, string $type): string
    {
        return match ($type) {
            'transactions', 'price' => $value < 0 ? 'red' : 'green',
            'rising' => 'green',
            'falling' => 'red',
            default => 'gray',
        };
    }
}

if (! function_exists('marketCondition')) {
    /**
     * @return array{label: string, color: string}
     */
    function marketCondition(float|int $transactions, float|int $price, float|int $fallingSales): array
    {
        if ($transactions < -20 && $price <= 0 && $fallingSales > 60) {
            return ['label' => 'Cooling', 'color' => 'red'];
        }

        if ($transactions < 0 && $price > 0) {
            return ['label' => 'Stabilising', 'color' => 'yellow'];
        }

        return ['label' => 'Expanding', 'color' => 'green'];
    }
}
