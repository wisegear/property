<?php

namespace App\Support\PropertyResearch;

class OfstedRating
{
    public function __construct(
        public readonly string $label,
        public readonly string $badgeClass,
        public readonly int $sortOrder,
    ) {}

    public static function from(mixed $value): self
    {
        $normalised = strtolower(trim((string) $value));

        return match ($normalised) {
            '1', 'outstanding' => new self('Outstanding', 'bg-green-100 text-green-800 border-green-200', 10),
            '2', 'good' => new self('Good', 'bg-blue-100 text-blue-800 border-blue-200', 20),
            '3', 'requires improvement' => new self('Requires improvement', 'bg-amber-100 text-amber-800 border-amber-200', 30),
            '4', 'inadequate' => new self('Inadequate', 'bg-red-100 text-red-800 border-red-200', 40),
            default => new self('No current Ofsted rating', 'bg-zinc-100 text-zinc-800 border-zinc-200', 50),
        };
    }
}
