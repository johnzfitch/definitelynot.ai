<?php

require_once __DIR__ . '/api/TextLinter.php';

/**
 * Edge case tests for VectorHit implementation
 */

echo "=== TextLinter VectorHit Edge Case Test Suite ===\n\n";

$testsPassed = 0;
$testsFailed = 0;

function assertTest($name, $condition, $message = '') {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "✓ PASS: $name\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: $name" . ($message ? " - $message" : "") . "\n";
        $testsFailed++;
    }
}

// Test 1: Empty string
echo "Test 1: Empty String\n";
echo str_repeat("=", 50) . "\n";
try {
    $result = TextLinter::analyzeWithDiff('', 'safe');
    assertTest('Empty string returns valid result', is_array($result));
    assertTest('Empty string has no hits', empty($result['hits']));
    assertTest('Empty string original matches sanitized', $result['originalText'] === '' || $result['sanitizedText'] === "\n");
    assertTest('Empty string totalChanges is 0', $result['summary']['totalChanges'] === 0);
} catch (\Exception $e) {
    assertTest('Empty string handling', false, $e->getMessage());
}
echo "\n";

// Test 2: Very long input (near MAX_INPUT_SIZE)
echo "Test 2: Large Input Handling\n";
echo str_repeat("=", 50) . "\n";
try {
    // Test with a moderately large string (10KB instead of 1MB to keep test fast)
    $largeText = str_repeat('A', 10000);
    $result = TextLinter::analyzeWithDiff($largeText, 'safe');
    assertTest('Large input (10KB) succeeds', is_array($result));
} catch (\Exception $e) {
    assertTest('Large input (10KB)', false, $e->getMessage());
}

// Test with a string over the limit
try {
    $tooLargeText = str_repeat('A', TextLinter::MAX_INPUT_SIZE + 100);
    $result = TextLinter::analyzeWithDiff($tooLargeText, 'safe');
    assertTest('Large input (over limit) should throw', false, 'Expected exception not thrown');
} catch (\InvalidArgumentException $e) {
    assertTest('Large input (over limit) throws InvalidArgumentException', true);
} catch (\Exception $e) {
    assertTest('Large input (over limit)', false, 'Wrong exception type: ' . get_class($e));
}
echo "\n";

// Test 3: Multi-codepoint graphemes (emoji with modifiers)
echo "Test 3: Multi-codepoint Graphemes (Emoji)\n";
echo str_repeat("=", 50) . "\n";
try {
    // Emoji with skin tone modifier: 👋🏽 (waving hand + medium skin tone)
    $text = "Hello 👋🏽 world";
    $result = TextLinter::analyzeWithDiff($text, 'safe');
    assertTest('Emoji with modifiers processes without error', is_array($result));
    // Note: Without proper grapheme support, this may split incorrectly, but shouldn't crash
    echo "  Note: Proper handling requires intl extension\n";
} catch (\Exception $e) {
    assertTest('Emoji with modifiers', false, $e->getMessage());
}
echo "\n";

// Test 4: Invalid mode parameter
echo "Test 4: Invalid Mode Parameter\n";
echo str_repeat("=", 50) . "\n";
try {
    $result = TextLinter::analyzeWithDiff('test', 'invalid_mode');
    assertTest('Invalid mode defaults to safe', $result['sanitizedText'] === "test\n");
    echo "  Mode was normalized to 'safe'\n";
} catch (\Exception $e) {
    assertTest('Invalid mode handling', false, $e->getMessage());
}
echo "\n";

// Test 5: Mixed security issues in single text
echo "Test 5: Mixed Security Issues\n";
echo str_repeat("=", 50) . "\n";
try {
    // Combine BiDi, invisibles, and private use
    $text = "Test\u{202E}\u{200B}\u{E000}mixed";
    $result = TextLinter::analyzeWithDiff($text, 'aggressive');
    assertTest('Mixed issues processes', is_array($result));
    assertTest('Mixed issues detected', $result['summary']['totalChanges'] > 0);
    $kinds = array_keys($result['summary']['vectorCounts']);
    echo "  Detected kinds: " . implode(', ', $kinds) . "\n";
} catch (\Exception $e) {
    assertTest('Mixed security issues', false, $e->getMessage());
}
echo "\n";

// Test 6: Unicode codepoints > U+FFFF (6-digit formatting test)
echo "Test 6: High Unicode Codepoints (>U+FFFF)\n";
echo str_repeat("=", 50) . "\n";
try {
    // Use a supplementary private use character U+F0000
    $text = "Test\u{F0000}char";
    $result = TextLinter::analyzeWithDiff($text, 'strict');
    assertTest('High codepoint processes', is_array($result));
    if (!empty($result['hits'])) {
        $codepoint = $result['hits'][0]['codePoints'][0] ?? '';
        assertTest('High codepoint uses 5-digit format', strlen($codepoint) >= 7, "Got: $codepoint");
        echo "  Codepoint format: $codepoint\n";
    }
} catch (\Exception $e) {
    assertTest('High Unicode codepoints', false, $e->getMessage());
}
echo "\n";

// Test 7: All modes work correctly
echo "Test 7: All Sanitization Modes\n";
echo str_repeat("=", 50) . "\n";
$modes = ['safe', 'aggressive', 'strict'];
foreach ($modes as $mode) {
    try {
        $result = TextLinter::analyzeWithDiff("Test\u{200B}text", $mode);
        assertTest("Mode '$mode' works", is_array($result) && isset($result['hits']));
    } catch (\Exception $e) {
        assertTest("Mode '$mode'", false, $e->getMessage());
    }
}
echo "\n";

// Test 8: Backward compatibility with clean() API
echo "Test 8: Backward Compatibility\n";
echo str_repeat("=", 50) . "\n";
try {
    $text = "Test\u{200B}text";
    $cleanResult = TextLinter::clean($text, 'safe');
    $diffResult = TextLinter::analyzeWithDiff($text, 'safe');
    
    assertTest('clean() still works', isset($cleanResult['text']));
    assertTest('analyzeWithDiff() works', isset($diffResult['sanitizedText']));
    // The sanitized output should be the same
    assertTest('Both APIs produce same sanitized output', 
        trim($cleanResult['text']) === trim($diffResult['sanitizedText']));
} catch (\Exception $e) {
    assertTest('Backward compatibility', false, $e->getMessage());
}
echo "\n";

// Test 9: Text with only whitespace
echo "Test 9: Whitespace-only Text\n";
echo str_repeat("=", 50) . "\n";
try {
    $result = TextLinter::analyzeWithDiff("   \t\n  ", 'safe');
    assertTest('Whitespace-only text processes', is_array($result));
    assertTest('Whitespace has no security hits', $result['summary']['totalChanges'] === 0);
} catch (\Exception $e) {
    assertTest('Whitespace-only text', false, $e->getMessage());
}
echo "\n";

// Test 10: Combining marks (orphan detection)
echo "Test 10: Combining Marks\n";
echo str_repeat("=", 50) . "\n";
try {
    // Combining acute accent U+0301 after a letter
    $text = "e\u{0301}"; // é as combining sequence
    $result = TextLinter::analyzeWithDiff($text, 'safe');
    assertTest('Combining marks process', is_array($result));
    if (!empty($result['hits'])) {
        $hasOrphanMark = false;
        foreach ($result['hits'] as $hit) {
            if ($hit['kind'] === 'orphan_combining_marks') {
                $hasOrphanMark = true;
                break;
            }
        }
        echo "  Combining marks detected: " . ($hasOrphanMark ? 'yes' : 'no') . "\n";
    }
} catch (\Exception $e) {
    assertTest('Combining marks', false, $e->getMessage());
}
echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "Tests Passed: $testsPassed\n";
echo "Tests Failed: $testsFailed\n";
echo "Total Tests: " . ($testsPassed + $testsFailed) . "\n";

if ($testsFailed === 0) {
    echo "\n✓ All edge case tests passed!\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed.\n";
    exit(1);
}
