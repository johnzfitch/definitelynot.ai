#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/TextLinter.php';

/**
 * Performance Benchmark Suite for Cosmic Text Linter
 *
 * Measures throughput and latency for different input sizes and modes.
 */

class Benchmark
{
    private const WARMUP_ITERATIONS = 5;
    private const BENCHMARK_ITERATIONS = 50;

    public function run(): void
    {
        echo "⚡ Cosmic Text Linter - Performance Benchmark\n";
        echo str_repeat('=', 70) . "\n\n";

        echo "Warmup...";
        $this->warmup();
        echo " done\n\n";

        // Benchmark different input sizes
        $this->benchmarkBySize('Small (100 chars)', $this->generateInput(100));
        $this->benchmarkBySize('Medium (1KB)', $this->generateInput(1024));
        $this->benchmarkBySize('Large (10KB)', $this->generateInput(10240));
        $this->benchmarkBySize('Very Large (100KB)', $this->generateInput(102400));

        echo "\n";
        $this->benchmarkWorstCase();
        echo "\n";
        $this->benchmarkModeComparison();
    }

    private function warmup(): void
    {
        $input = $this->generateInput(1024);
        for ($i = 0; $i < self::WARMUP_ITERATIONS; $i++) {
            TextLinter::clean($input, 'safe');
        }
    }

    private function benchmarkBySize(string $label, string $input): void
    {
        echo str_pad($label, 30) . " | ";

        $times = [];
        for ($i = 0; $i < self::BENCHMARK_ITERATIONS; $i++) {
            $start = microtime(true);
            TextLinter::clean($input, 'aggressive');
            $times[] = microtime(true) - $start;
        }

        $this->reportStats($times, strlen($input));
    }

    private function benchmarkWorstCase(): void
    {
        echo "Worst-case scenarios:\n";

        // Heavy Unicode with multiple issues
        $worstCase = $this->generateWorstCaseInput(1024);
        echo str_pad("  Mixed attacks (1KB)", 30) . " | ";

        $times = [];
        for ($i = 0; $i < self::BENCHMARK_ITERATIONS; $i++) {
            $start = microtime(true);
            TextLinter::clean($worstCase, 'aggressive');
            $times[] = microtime(true) - $start;
        }

        $this->reportStats($times, strlen($worstCase));
    }

    private function benchmarkModeComparison(): void
    {
        echo "Mode comparison (1KB input):\n";
        $input = $this->generateInput(1024);

        foreach (['safe', 'aggressive', 'strict'] as $mode) {
            echo str_pad("  Mode: {$mode}", 30) . " | ";

            $times = [];
            for ($i = 0; $i < self::BENCHMARK_ITERATIONS; $i++) {
                $start = microtime(true);
                TextLinter::clean($input, $mode);
                $times[] = microtime(true) - $start;
            }

            $this->reportStats($times, strlen($input));
        }
    }

    private function reportStats(array $times, int $bytes): void
    {
        sort($times);
        $count = count($times);

        $avg = array_sum($times) / $count;
        $min = $times[0];
        $max = $times[$count - 1];
        $p50 = $times[(int)($count * 0.5)];
        $p95 = $times[(int)($count * 0.95)];
        $p99 = $times[(int)($count * 0.99)];

        $throughputMBps = ($bytes / 1048576) / $avg;

        printf(
            "avg:%6.2fms | p50:%6.2fms | p95:%6.2fms | p99:%6.2fms | %.2f MB/s\n",
            $avg * 1000,
            $p50 * 1000,
            $p95 * 1000,
            $p99 * 1000,
            $throughputMBps
        );
    }

    private function generateInput(int $byteSize): string
    {
        $text = '';
        $words = [
            'Lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur',
            'adipiscing', 'elit', 'sed', 'do', 'eiusmod', 'tempor',
            'incididunt', 'ut', 'labore', 'et', 'dolore', 'magna', 'aliqua'
        ];

        while (strlen($text) < $byteSize) {
            $text .= $words[array_rand($words)] . ' ';
            // Add some Unicode variety
            if (rand(0, 20) === 0) {
                $text .= "\u{2013} "; // en-dash
            }
            if (rand(0, 30) === 0) {
                $text .= "\n";
            }
        }

        return substr($text, 0, $byteSize);
    }

    private function generateWorstCaseInput(int $byteSize): string
    {
        $text = '';
        $attacks = [
            "\u{200B}",        // Zero-width space
            "\u{202E}",        // BiDi override
            "\u{202C}",        // BiDi pop
            "а",               // Cyrillic a (homoglyph)
            "\u{FEFF}",        // BOM
            "&nbsp;",          // HTML entity
            "\u{0661}",        // Arabic-Indic digit
            "\u{200D}",        // ZWJ
            "\u{0301}",        // Combining acute
        ];

        $base = 'Hello world test ';

        while (strlen($text) < $byteSize) {
            $text .= $base;
            // Inject attack characters
            if (rand(0, 3) === 0) {
                $text .= $attacks[array_rand($attacks)];
            }
        }

        return substr($text, 0, $byteSize);
    }
}

// Run benchmark
$benchmark = new Benchmark();
$benchmark->run();
