<?php

namespace App\Console\Commands;

use App\Http\Controllers\EpcPostcodeController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Throwable;

class WarmEpcPostcodes extends Command
{
    protected $signature = 'epc:warm-postcodes
                            {--regime=all : all|england_wales|scotland}
                            {--limit=0 : Limit the number of postcodes to warm per regime (0 = all)}';

    protected $description = 'Warm postcode EPC caches for all indexed postcodes.';

    public function handle(): int
    {
        $indexPath = public_path('data/epc-postcodes.json');
        if (! File::exists($indexPath)) {
            $this->error('Postcode index not found at '.$indexPath.'. Run epc:build-postcode-index first.');

            return self::FAILURE;
        }

        $index = json_decode((string) File::get($indexPath), true);
        if (! is_array($index)) {
            $this->error('Postcode index JSON is invalid.');

            return self::FAILURE;
        }

        $regimeOption = strtolower((string) $this->option('regime'));
        if (! in_array($regimeOption, ['all', 'england_wales', 'scotland'], true)) {
            $this->error('Invalid --regime option. Use all|england_wales|scotland.');

            return self::FAILURE;
        }

        $limit = max(0, (int) $this->option('limit'));
        $targets = $this->buildTargets($index, $regimeOption, $limit);
        $total = count($targets);

        if ($total === 0) {
            $this->warn('No indexed postcodes found to warm.');

            return self::SUCCESS;
        }

        $this->info('Warming '.$total.' EPC postcode caches...');
        $this->output->progressStart($total);
        $startedAt = microtime(true);

        $controller = app(EpcPostcodeController::class);
        $warmed = 0;
        $failed = 0;

        foreach ($targets as $target) {
            try {
                $controller->warmPostcodeCache($target['regime'], $target['postcode']);
                $warmed++;
            } catch (Throwable $throwable) {
                $failed++;
                $this->newLine();
                $this->error("Failed warming {$target['regime']} {$target['postcode']}: ".$throwable->getMessage());
            }

            $this->output->progressAdvance();
        }

        $this->output->progressFinish();
        $this->newLine();

        Cache::put('epc:postcode:last_warm', now()->toIso8601String(), now()->addDays(45));

        $elapsed = round(microtime(true) - $startedAt, 2);
        $this->info("Warm complete in {$elapsed}s");
        $this->line('Warmed: '.number_format($warmed));
        $this->line('Failed: '.number_format($failed));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<int, array{regime:string,postcode:string}>
     */
    private function buildTargets(array $index, string $regimeOption, int $limit): array
    {
        $targets = [];
        $regimes = ['england_wales', 'scotland'];
        if ($regimeOption !== 'all') {
            $regimes = [$regimeOption];
        }

        foreach ($regimes as $regime) {
            $postcodes = data_get($index, 'postcodes.'.$regime, []);
            if (! is_array($postcodes)) {
                continue;
            }

            $cleanPostcodes = collect($postcodes)
                ->map(fn ($postcode) => strtoupper(trim((string) $postcode)))
                ->filter(fn (string $postcode) => $postcode !== '')
                ->unique()
                ->sort()
                ->values();

            if ($limit > 0) {
                $cleanPostcodes = $cleanPostcodes->take($limit)->values();
            }

            foreach ($cleanPostcodes as $postcode) {
                $targets[] = [
                    'regime' => $regime,
                    'postcode' => $postcode,
                ];
            }
        }

        return $targets;
    }
}
