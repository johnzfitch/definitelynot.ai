<?php

require_once __DIR__ . '/api/TextLinter.php';

/**
 * Test script for VectorHit-based diff and reporting layer
 */

echo "=== TextLinter VectorHit Test Suite ===\n\n";

// Test 1: BiDi Controls
echo "Test 1: BiDi Controls\n";
echo str_repeat("=", 50) . "\n";
$text1 = "Hello \u{202E}world\u{202C} test";
$result1 = TextLinter::analyzeWithDiff($text1, 'aggressive');

echo "Original: " . json_encode($result1['originalText']) . "\n";
echo "Sanitized: " . json_encode($result1['sanitizedText']) . "\n";
echo "Total changes: {$result1['summary']['totalChanges']}\n";
echo "Vector counts:\n";
foreach ($result1['summary']['vectorCounts'] as $kind => $count) {
    echo "  - $kind: $count\n";
}
echo "\nHits:\n";
foreach ($result1['hits'] as $hit) {
    echo "  [{$hit['severity']}] {$hit['kind']}: {$hit['note']}\n";
    echo "    Original slice: " . json_encode($hit['originalSlice']) . "\n";
    echo "    Sanitized slice: " . json_encode($hit['sanitizedSlice']) . "\n";
    echo "    Codepoints: " . implode(', ', $hit['codePoints']) . "\n";
}
echo "\n\n";

// Test 2: Invisible Characters
echo "Test 2: Invisible Characters\n";
echo str_repeat("=", 50) . "\n";
$text2 = "Test\u{200B}invisible\u{200C}chars\u{FEFF}here";
$result2 = TextLinter::analyzeWithDiff($text2, 'aggressive');

echo "Original: " . json_encode($result2['originalText']) . "\n";
echo "Sanitized: " . json_encode($result2['sanitizedText']) . "\n";
echo "Total changes: {$result2['summary']['totalChanges']}\n";
echo "Vector counts:\n";
foreach ($result2['summary']['vectorCounts'] as $kind => $count) {
    echo "  - $kind: $count\n";
}
echo "\nHits:\n";
foreach ($result2['hits'] as $hit) {
    echo "  [{$hit['severity']}] {$hit['kind']}: {$hit['note']}\n";
    echo "    Original range: [{$hit['originalRange']['startGrapheme']}, {$hit['originalRange']['endGrapheme']})\n";
    echo "    Sanitized range: [{$hit['sanitizedRange']['startGrapheme']}, {$hit['sanitizedRange']['endGrapheme']})\n";
    echo "    Codepoints: " . implode(', ', $hit['codePoints']) . "\n";
}
echo "\n\n";

// Test 3: Non-ASCII Digits
echo "Test 3: Non-ASCII Digits\n";
echo str_repeat("=", 50) . "\n";
$text3 = "Price: ١٢٣ dollars"; // Arabic-Indic digits
$result3 = TextLinter::analyzeWithDiff($text3, 'aggressive');

echo "Original: " . json_encode($result3['originalText']) . "\n";
echo "Sanitized: " . json_encode($result3['sanitizedText']) . "\n";
echo "Total changes: {$result3['summary']['totalChanges']}\n";
echo "Vector counts:\n";
foreach ($result3['summary']['vectorCounts'] as $kind => $count) {
    echo "  - $kind: $count\n";
}
if (!empty($result3['hits'])) {
    echo "\nHits:\n";
    foreach ($result3['hits'] as $hit) {
        echo "  [{$hit['severity']}] {$hit['kind']}: {$hit['note']}\n";
        echo "    Original slice: " . json_encode($hit['originalSlice']) . "\n";
        echo "    Sanitized slice: " . json_encode($hit['sanitizedSlice']) . "\n";
    }
}
echo "\n\n";

// Test 4: Private Use Area (strict mode)
echo "Test 4: Private Use Area (strict mode)\n";
echo str_repeat("=", 50) . "\n";
$text4 = "Test\u{E000}private\u{E001}use";
$result4 = TextLinter::analyzeWithDiff($text4, 'strict');

echo "Original: " . json_encode($result4['originalText']) . "\n";
echo "Sanitized: " . json_encode($result4['sanitizedText']) . "\n";
echo "Total changes: {$result4['summary']['totalChanges']}\n";
if (!empty($result4['summary']['vectorCounts'])) {
    echo "Vector counts:\n";
    foreach ($result4['summary']['vectorCounts'] as $kind => $count) {
        echo "  - $kind: $count\n";
    }
}
if (!empty($result4['hits'])) {
    echo "\nHits:\n";
    foreach ($result4['hits'] as $hit) {
        echo "  [{$hit['severity']}] {$hit['kind']}: {$hit['note']}\n";
        echo "    Codepoints: " . implode(', ', $hit['codePoints']) . "\n";
    }
}
echo "\n\n";

// Test 5: Clean text (no changes)
echo "Test 5: Clean text (no changes)\n";
echo str_repeat("=", 50) . "\n";
$text5 = "Hello world! This is clean text.";
$result5 = TextLinter::analyzeWithDiff($text5, 'aggressive');

echo "Original: " . json_encode($result5['originalText']) . "\n";
echo "Sanitized: " . json_encode($result5['sanitizedText']) . "\n";
echo "Total changes: {$result5['summary']['totalChanges']}\n";
echo "Hits: " . (empty($result5['hits']) ? "None" : count($result5['hits'])) . "\n";
echo "\n\n";

// Test 6: Verify existing clean() API still works
echo "Test 6: Verify existing clean() API\n";
echo str_repeat("=", 50) . "\n";
$text6 = "Test\u{200B}with\u{202E}controls";
$cleanResult = TextLinter::clean($text6, 'aggressive');

echo "Input: " . json_encode($text6) . "\n";
echo "Output: " . json_encode($cleanResult['text']) . "\n";
echo "Stats keys: " . implode(', ', array_keys($cleanResult['stats'])) . "\n";
echo "Had BiDi controls: " . ($cleanResult['stats']['advisories']['had_bidi_controls'] ? 'yes' : 'no') . "\n";
echo "Had default ignorables: " . ($cleanResult['stats']['advisories']['had_default_ignorables'] ? 'yes' : 'no') . "\n";
echo "\n\n";

echo "=== All tests completed ===\n";
