#!/usr/bin/env php
<?php

/**
 * Sample Data Generator for VectorHit Diff Layer
 *
 * Generates comprehensive sample data demonstrating various
 * security vectors that the VectorHit diff layer can detect.
 *
 * Usage:
 *   php generate_sample_data.php [output_file]
 *
 * If no output file is specified, outputs to stdout in JSON format.
 */

require_once __DIR__ . '/api/TextLinter.php';

// Sample texts with various security issues
$samples = [
    'clean_text' => [
        'description' => 'Clean ASCII text with no security issues',
        'text' => 'This is a completely clean text with no security issues.',
        'expected_hits' => 0,
        'categories' => ['baseline'],
    ],

    'bidi_controls' => [
        'description' => 'Text with BiDi override controls (RLO/PDF)',
        'text' => "Hello \u{202E}world\u{202C} test",
        'expected_hits' => 1,
        'categories' => ['bidi', 'high_severity'],
    ],

    'bidi_spoofing' => [
        'description' => 'Potential BiDi spoofing attack on filename',
        'text' => "invoice_\u{202E}gpj.exe\u{202C}",
        'expected_hits' => 1,
        'categories' => ['bidi', 'high_severity', 'spoofing'],
    ],

    'invisible_chars' => [
        'description' => 'Text with various invisible characters',
        'text' => "Test\u{200B}zero\u{200C}width\u{200D}joiners\u{FEFF}BOM",
        'expected_hits' => 1,
        'categories' => ['invisible', 'medium_severity'],
    ],

    'zero_width_spaces' => [
        'description' => 'Password with hidden zero-width spaces',
        'text' => "pass\u{200B}word\u{200B}123",
        'expected_hits' => 1,
        'categories' => ['invisible', 'credential_stuffing'],
    ],

    'tag_chars' => [
        'description' => 'Text with TAG block characters',
        'text' => "Test\u{E0061}\u{E0062}tag\u{E007F}chars",
        'expected_hits' => 1,
        'categories' => ['tag', 'high_severity'],
    ],

    'noncharacters' => [
        'description' => 'Text with Unicode noncharacters',
        'text' => "Test\u{FDD0}nonchar\u{FFFE}here",
        'expected_hits' => 1,
        'categories' => ['noncharacter', 'high_severity'],
    ],

    'private_use_area' => [
        'description' => 'Text with Private Use Area characters',
        'text' => "Test\u{E000}private\u{E001}use\u{F8FF}area",
        'expected_hits' => 1,
        'categories' => ['private_use', 'medium_severity'],
    ],

    'non_ascii_digits' => [
        'description' => 'Text with non-ASCII digit characters',
        'text' => "Price: ١٢٣ dollars (Arabic-Indic digits)",
        'expected_hits' => 1,
        'categories' => ['digits', 'medium_severity'],
    ],

    'mixed_scripts' => [
        'description' => 'Mixed script potential confusables',
        'text' => "pаypal.com", // Cyrillic 'а' in 'paypal'
        'expected_hits' => 0, // Note: confusables detection is complex
        'categories' => ['confusable', 'spoofing'],
    ],

    'emoji_with_zwj' => [
        'description' => 'Multi-codepoint emoji with ZWJ sequences',
        'text' => "Family emoji: 👨‍👩‍👧‍👦",
        'expected_hits' => 0, // In 'safe' mode, ZWJ preserved
        'categories' => ['emoji', 'grapheme_boundary'],
    ],

    'combining_marks' => [
        'description' => 'Text with combining character sequences',
        'text' => "Cafe\u{0301} (combining acute accent)",
        'expected_hits' => 0, // Properly formed combining marks are safe
        'categories' => ['combining', 'normalization'],
    ],

    'multiple_issues' => [
        'description' => 'Text with multiple security vectors',
        'text' => "Test\u{200B}\u{202E}multiple\u{E000}security\u{0660}issues",
        'expected_hits' => 3, // Multiple different kinds
        'categories' => ['multiple', 'complex'],
    ],

    'sql_injection_attempt' => [
        'description' => 'SQL injection attempt with invisibles',
        'text' => "admin'\u{200B}--\u{200C}comment",
        'expected_hits' => 1,
        'categories' => ['invisible', 'injection'],
    ],

    'homograph_attack' => [
        'description' => 'Homograph attack using similar-looking characters',
        'text' => "αpple.com", // Greek alpha instead of 'a'
        'expected_hits' => 0, // Basic detection
        'categories' => ['confusable', 'spoofing', 'homograph'],
    ],

    'right_to_left_override' => [
        'description' => 'Right-to-Left Override in email',
        'text' => "user@\u{202E}moc.evil\u{202C}trusted.com",
        'expected_hits' => 1,
        'categories' => ['bidi', 'high_severity', 'email_spoofing'],
    ],

    'format_characters' => [
        'description' => 'Various format control characters',
        'text' => "Test\u{00AD}soft\u{180E}hyphen\u{2060}word\u{2061}joiner",
        'expected_hits' => 1,
        'categories' => ['invisible', 'format'],
    ],

    'variation_selectors' => [
        'description' => 'Text with variation selectors',
        'text' => "Text\u{FE0E}emoji\u{FE0F}style",
        'expected_hits' => 1,
        'categories' => ['invisible', 'presentation'],
    ],

    'surrogate_pairs' => [
        'description' => 'Text with high Unicode codepoints (emoji)',
        'text' => "Smile 😀 test 🎉 celebration",
        'expected_hits' => 0,
        'categories' => ['emoji', 'surrogate'],
    ],

    'mixed_normalization' => [
        'description' => 'Text requiring Unicode normalization',
        'text' => "café vs cafe\u{0301}",
        'expected_hits' => 0, // Normalized safely
        'categories' => ['normalization'],
    ],
];

/**
 * Process samples and generate analysis
 */
function generateSampleData(array $samples, string $mode = 'aggressive'): array
{
    $results = [];

    foreach ($samples as $key => $sample) {
        echo "Processing: {$sample['description']}...\n";

        $analysis = TextLinter::analyzeWithDiff($sample['text'], $mode);

        $results[$key] = [
            'description' => $sample['description'],
            'categories' => $sample['categories'],
            'expected_hits' => $sample['expected_hits'],
            'input' => [
                'text' => $sample['text'],
                'hex' => bin2hex($sample['text']),
                'length' => mb_strlen($sample['text'], 'UTF-8'),
            ],
            'output' => [
                'text' => $analysis['sanitizedText'],
                'length' => mb_strlen($analysis['sanitizedText'], 'UTF-8'),
            ],
            'analysis' => [
                'totalChanges' => $analysis['summary']['totalChanges'],
                'vectorCounts' => $analysis['summary']['vectorCounts'],
                'hits' => array_map(function($hit) {
                    return [
                        'kind' => $hit['kind'],
                        'severity' => $hit['severity'],
                        'note' => $hit['note'],
                        'originalSlice' => $hit['originalSlice'],
                        'sanitizedSlice' => $hit['sanitizedSlice'],
                        'codePoints' => $hit['codePoints'],
                    ];
                }, $analysis['hits']),
            ],
            'validation' => [
                'meets_expectation' => count($analysis['hits']) >= $sample['expected_hits'],
                'actual_hits' => count($analysis['hits']),
            ],
        ];
    }

    return $results;
}

/**
 * Generate summary statistics
 */
function generateSummary(array $results): array
{
    $summary = [
        'total_samples' => count($results),
        'samples_with_hits' => 0,
        'total_hits' => 0,
        'vector_kinds_seen' => [],
        'severity_distribution' => [
            'block' => 0,
            'warn' => 0,
            'info' => 0,
        ],
        'category_distribution' => [],
    ];

    foreach ($results as $result) {
        if ($result['analysis']['totalChanges'] > 0) {
            $summary['samples_with_hits']++;
        }

        $summary['total_hits'] += $result['analysis']['totalChanges'];

        foreach ($result['analysis']['hits'] as $hit) {
            $kind = $hit['kind'];
            $severity = $hit['severity'];

            $summary['vector_kinds_seen'][$kind] = ($summary['vector_kinds_seen'][$kind] ?? 0) + 1;
            $summary['severity_distribution'][$severity]++;
        }

        foreach ($result['categories'] as $category) {
            $summary['category_distribution'][$category] = ($summary['category_distribution'][$category] ?? 0) + 1;
        }
    }

    return $summary;
}

// Main execution
echo "=== VectorHit Sample Data Generator ===\n\n";

// Check command line arguments
$outputFile = $argv[1] ?? null;

// Generate sample data
$results = generateSampleData($samples, 'aggressive');
$summary = generateSummary($results);

// Prepare output
$output = [
    'metadata' => [
        'generated_at' => date('c'),
        'generator_version' => '1.0.0',
        'textlinter_version' => TextLinter::VERSION,
        'mode' => 'aggressive',
    ],
    'summary' => $summary,
    'samples' => $results,
];

// Output results
$jsonOutput = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($outputFile) {
    file_put_contents($outputFile, $jsonOutput);
    echo "\n✓ Sample data written to: $outputFile\n";
    echo "  Total samples: {$summary['total_samples']}\n";
    echo "  Samples with security issues: {$summary['samples_with_hits']}\n";
    echo "  Total vector hits: {$summary['total_hits']}\n";
} else {
    echo "\n" . $jsonOutput . "\n";
}

echo "\n✓ Sample data generation complete!\n";
exit(0);
