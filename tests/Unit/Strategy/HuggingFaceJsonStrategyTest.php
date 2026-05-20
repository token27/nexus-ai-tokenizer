<?php

declare(strict_types=1);

namespace Token27\Tokenizer\Tests\Unit\Strategy;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Token27\Tokenizer\Exception\TokenizerLoadException;
use Token27\Tokenizer\Strategy\HuggingFaceJsonStrategy;

/**
 * Tests for HuggingFaceJsonStrategy.
 *
 * Uses a small synthetic tokenizer.json fixture to test the BPE algorithm
 * without requiring the full DeepSeek 128K vocabulary to be present.
 */
final class HuggingFaceJsonStrategyTest extends TestCase
{
    private string $fixtureDir;
    private string $fixturePath;

    protected function setUp(): void
    {
        $this->fixtureDir  = sys_get_temp_dir() . '/nexus_tokenizer_test_' . getmypid();
        $this->fixturePath = $this->fixtureDir . '/tokenizer.json';

        if (!is_dir($this->fixtureDir)) {
            mkdir($this->fixtureDir, 0755, true);
        }

        $this->writeFixtureTokenizer();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->fixtureDir)) {
            foreach (glob($this->fixtureDir . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($this->fixtureDir);
        }
    }

    #[Test]
    public function it_has_the_correct_strategy_name(): void
    {
        $strategy = new HuggingFaceJsonStrategy($this->fixturePath);
        self::assertSame('hf_json_bpe', $strategy->getStrategyName());
    }

    #[Test]
    public function it_throws_when_file_does_not_exist(): void
    {
        $this->expectException(TokenizerLoadException::class);
        $this->expectExceptionMessageMatches('/not found/');

        $strategy = new HuggingFaceJsonStrategy('/nonexistent/path/tokenizer.json');
        $strategy->count('hello', 'test-model');
    }

    #[Test]
    public function it_throws_for_non_bpe_model_type(): void
    {
        $path = $this->fixtureDir . '/unigram.json';
        file_put_contents($path, json_encode([
            'model' => ['type' => 'Unigram', 'vocab' => [], 'merges' => []],
        ]));

        $this->expectException(TokenizerLoadException::class);
        $this->expectExceptionMessageMatches('/BPE/');

        $strategy = new HuggingFaceJsonStrategy($path);
        $strategy->count('hello', 'test-model');
    }

    #[Test]
    public function it_counts_tokens_with_metaspace_pretokenizer(): void
    {
        $strategy = new HuggingFaceJsonStrategy($this->fixturePath);
        $count    = $strategy->count('Hello world', 'test-model');

        self::assertGreaterThan(0, $count->count());
        self::assertFalse($count->isApproximate());
        self::assertSame('hf_json_bpe', $count->strategy());
    }

    #[Test]
    public function it_returns_zero_for_empty_string(): void
    {
        $strategy = new HuggingFaceJsonStrategy($this->fixturePath);
        $count    = $strategy->count('', 'test-model');
        self::assertSame(0, $count->count());
    }

    #[Test]
    public function it_counts_chat_with_overhead(): void
    {
        $strategy = new HuggingFaceJsonStrategy($this->fixturePath);
        $messages = [
            ['role' => 'system', 'content' => 'You are helpful.'],
            ['role' => 'user',   'content' => 'Hello!'],
        ];

        $count = $strategy->countChat($messages, 'test-model');
        self::assertGreaterThan(0, $count->overheadTokens());
        self::assertGreaterThan(0, $count->count());
    }

    #[Test]
    public function it_uses_engine_cache_for_same_path(): void
    {
        // Loading the same path twice should use the static cache
        $strategy1 = new HuggingFaceJsonStrategy($this->fixturePath);
        $strategy2 = new HuggingFaceJsonStrategy($this->fixturePath);

        $count1 = $strategy1->count('Hello', 'model-a');
        $count2 = $strategy2->count('Hello', 'model-b');

        self::assertSame($count1->count(), $count2->count());
    }

    private function writeFixtureTokenizer(): void
    {
        // Minimal valid tokenizer.json for testing BPE with Metaspace pre-tokenizer.
        // Vocabulary covers single chars and common merges for "Hello world".
        $fixture = [
            'version'       => '1.0',
            'added_tokens'  => [],
            'normalizer'    => null,
            'pre_tokenizer' => [
                'type'           => 'Metaspace',
                'replacement'    => '▁',
                'prepend_scheme' => 'first',
                'split'          => true,
            ],
            'model' => [
                'type'          => 'BPE',
                'dropout'       => null,
                'byte_fallback' => false,
                'vocab'         => $this->buildMinimalVocab(),
                'merges'        => $this->buildMinimalMerges(),
            ],
        ];

        file_put_contents($this->fixturePath, json_encode($fixture, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, int>
     */
    private function buildMinimalVocab(): array
    {
        $chars = ['▁', 'H', 'e', 'l', 'o', 'w', 'r', 'd', ' ', '!'];
        $vocab = [];
        $id    = 0;

        foreach ($chars as $char) {
            $vocab[$char] = $id++;
        }

        // Add merged tokens
        $vocab['▁H']     = $id++;
        $vocab['▁He']    = $id++;
        $vocab['▁Hel']   = $id++;
        $vocab['▁Hell']  = $id++;
        $vocab['▁Hello'] = $id++;
        $vocab['▁w']     = $id++;
        $vocab['▁wo']    = $id++;
        $vocab['▁wor']   = $id++;
        $vocab['▁worl']  = $id++;
        $vocab['▁world'] = $id++;

        return $vocab;
    }

    /**
     * @return list<string>
     */
    private function buildMinimalMerges(): array
    {
        return [
            '▁ H',      // rank 0: ▁ + H → ▁H
            '▁H e',     // rank 1: ▁H + e → ▁He
            '▁He l',    // rank 2: ▁He + l → ▁Hel
            '▁Hel l',   // rank 3: ▁Hel + l → ▁Hell
            '▁Hell o',  // rank 4: ▁Hell + o → ▁Hello
            '▁ w',      // rank 5: ▁ + w → ▁w
            '▁w o',     // rank 6: ▁w + o → ▁wo
            '▁wo r',    // rank 7: ▁wo + r → ▁wor
            '▁wor l',   // rank 8: ▁wor + l → ▁worl
            '▁worl d',  // rank 9: ▁worl + d → ▁world
        ];
    }
}
