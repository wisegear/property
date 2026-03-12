<?php

namespace Tests\Feature;

use App\Services\InsightWriter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InsightWriterAiTest extends TestCase
{
    public function test_generate_with_ai_returns_openai_text_when_request_succeeds(): void
    {
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.model', 'gpt-5-nano');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [
                    [
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => 'Median property prices in NW8 rose 18% in 01 Feb 2025 to 31 Jan 2026 based on 112 recorded sales.',
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $writer = new InsightWriter;

        $result = $writer->generateWithAI([
            'area' => 'NW8',
            'metric' => 'average property price',
            'change' => '+18%',
            'transactions' => 112,
            'period' => 'Feb 2026',
            'insight_type' => 'price_spike',
        ]);

        $this->assertSame(
            'Median property prices in NW8 rose 18% in 01 Feb 2025 to 31 Jan 2026 based on 112 recorded sales.',
            $result
        );
    }

    public function test_generate_with_ai_falls_back_to_template_text_when_openai_fails(): void
    {
        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.model', 'gpt-5-nano');
        config()->set('services.openai.base_url', 'https://api.openai.com/v1');

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response(['error' => 'upstream failure'], 500),
        ]);

        $writer = new InsightWriter;

        $result = $writer->generateWithAI([
            'area' => 'NW8',
            'metric' => 'average property price',
            'change' => '+18%',
            'transactions' => 112,
            'period' => 'Feb 2026',
            'insight_type' => 'price_spike',
        ]);

        $this->assertSame(
            'Median property prices in NW8 rose 18% in Feb 2026 based on 112 recorded sales.',
            $result
        );
    }
}
