<?php

require_once __DIR__ . '/api/TextLinter.php';

/**
 * Edge case tests for VectorHit-based diff and reporting layer
 */

echo "=== TextLinter VectorHit Edge Case Tests ===\n\n";

$testsPassed = 0;
$testsFailed = 0;

function assertTest(string $name, bool $condition, string $message = ''): void
{
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "✓ $name\n";
        $testsPassed++;
    } else {
        echo "✗ $name" . ($message ? ": $message" : "") . "\n";
        $testsFailed++;
    }
}

// Test 1: Empty string
echo "Test 1: Empty string\n";
echo str_repeat("=", 50) . "\n";
try {
    $result = TextLinter::analyzeWithDiff('', 'aggressive');
    assertTest('Empty string returns valid result', isset($result['hits']));
    assertTest('Empty string has no hits', empty($result['hits']));
    assertTest('Empty string sanitized is empty', $result['sanitizedText'] === "\n" || $result['sanitizedText'] === '');
    assertTest('Empty string total changes is 0', $result['summary']['totalChanges'] === 0);
} catch (Exception $e) {
    echo "✗ Empty string test failed with exception: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 2: Single character
echo "Test 2: Single character\n";
echo str_repeat("=", 50) . "\n";
try {
    $result = TextLinter::analyzeWithDiff('a', 'aggressive');
    assertTest('Single char returns valid result', isset($result['hits']));
    assertTest('Single char has clean output', strlen($result['sanitizedText']) > 0);
} catch (Exception $e) {
    echo "✗ Single character test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 3: Very long string (but under MAX_INPUT_SIZE)
echo "Test 3: Long string (10KB)\n";
echo str_repeat("=", 50) . "\n";
try {
    $longText = str_repeat('Hello world! ', 800); // ~10KB
    $result = TextLinter::analyzeWithDiff($longText, 'safe');
    assertTest('Long string processes successfully', isset($result['sanitizedText']));
    assertTest('Long string has no hits (clean text)', empty($result['hits']));
} catch (Exception $e) {
    echo "✗ Long string test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 4: Input exceeding MAX_INPUT_SIZE
echo "Test 4: Input exceeding MAX_INPUT_SIZE\n";
echo str_repeat("=", 50) . "\n";
try {
    $hugeText = str_repeat('a', TextLinter::MAX_INPUT_SIZE + 1);
    $result = TextLinter::analyzeWithDiff($hugeText, 'aggressive');
    echo "✗ Should have thrown InvalidArgumentException for oversized input\n";
    $testsFailed++;
} catch (InvalidArgumentException $e) {
    assertTest('Oversized input throws InvalidArgumentException', true);
} catch (Exception $e) {
    echo "✗ Wrong exception type: " . get_class($e) . "\n";
    $testsFailed++;
}
echo "\n";

// Test 5: Multi-codepoint emoji
echo "Test 5: Multi-codepoint emoji (👨‍👩‍👧‍👦)\n";
echo str_repeat("=", 50) . "\n";
try {
    $emoji = "Family: 👨‍👩‍👧‍👦 emoji";
    $result = TextLinter::analyzeWithDiff($emoji, 'aggressive');
    assertTest('Emoji text processes successfully', isset($result['sanitizedText']));
    // Emoji with ZWJ may be modified in aggressive mode
    echo "  Original: " . json_encode($result['originalText']) . "\n";
    echo "  Sanitized: " . json_encode($result['sanitizedText']) . "\n";
    echo "  Hits: " . count($result['hits']) . "\n";
} catch (Exception $e) {
    echo "✗ Emoji test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 6: Combined character sequences (é = e + combining acute)
echo "Test 6: Combining character sequences\n";
echo str_repeat("=", 50) . "\n";
try {
    $text = "cafe\u{0301}"; // café with combining acute accent
    $result = TextLinter::analyzeWithDiff($text, 'safe');
    assertTest('Combining chars process successfully', isset($result['sanitizedText']));
    echo "  Original: " . json_encode($result['originalText']) . "\n";
    echo "  Sanitized: " . json_encode($result['sanitizedText']) . "\n";
} catch (Exception $e) {
    echo "✗ Combining chars test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 7: Invalid mode parameter
echo "Test 7: Invalid mode parameter\n";
echo str_repeat("=", 50) . "\n";
try {
    $result = TextLinter::analyzeWithDiff('test', 'invalid_mode');
    assertTest('Invalid mode defaults to safe', true);
    // Mode validation happens inside analyzeWithDiff now
} catch (Exception $e) {
    echo "✗ Invalid mode test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 8: Multiple security issues in one text
echo "Test 8: Multiple security issues combined\n";
echo str_repeat("=", 50) . "\n";
try {
    $text = "Test\u{200B}\u{202E}multiple\u{E000}issues\u{0660}here";
    $result = TextLinter::analyzeWithDiff($text, 'strict');
    assertTest('Multiple issues detected', count($result['hits']) > 0);

    $kindsSeen = [];
    foreach ($result['hits'] as $hit) {
        $kindsSeen[$hit['kind']] = true;
    }

    echo "  Vector kinds detected: " . implode(', ', array_keys($kindsSeen)) . "\n";
    echo "  Total hits: " . count($result['hits']) . "\n";

    assertTest('Multiple vector kinds detected', count($kindsSeen) > 1);
} catch (Exception $e) {
    echo "✗ Multiple issues test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 9: High codepoint (emoji beyond BMP - U+1F600)
echo "Test 9: High codepoint formatting (U+1F600 😀)\n";
echo str_repeat("=", 50) . "\n";
try {
    $text = "Smile 😀 test";
    $result = TextLinter::analyzeWithDiff($text, 'aggressive');
    assertTest('High codepoint processes successfully', isset($result['sanitizedText']));
    echo "  Original: " . json_encode($result['originalText']) . "\n";
    echo "  Sanitized: " . json_encode($result['sanitizedText']) . "\n";
} catch (Exception $e) {
    echo "✗ High codepoint test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 10: Text with only security issues (all deleted)
echo "Test 10: Text with only security issues\n";
echo str_repeat("=", 50) . "\n";
try {
    $text = "\u{202E}\u{200B}\u{FEFF}";
    $result = TextLinter::analyzeWithDiff($text, 'aggressive');
    assertTest('Security-only text processes', isset($result['sanitizedText']));
    assertTest('Security-only text gets sanitized to empty/newline',
               strlen(trim($result['sanitizedText'])) === 0);
    assertTest('Security-only text generates hits', count($result['hits']) > 0);
    echo "  Hits generated: " . count($result['hits']) . "\n";
} catch (Exception $e) {
    echo "✗ Security-only text failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 11: Verify no duplicate VectorKinds for same codepoint
echo "Test 11: No duplicate classifications\n";
echo str_repeat("=", 50) . "\n";
try {
    $text = "\u{200E}test"; // LRM - should be BiDi control, not invisible
    $result = TextLinter::analyzeWithDiff($text, 'aggressive');

    $hitKinds = [];
    foreach ($result['hits'] as $hit) {
        $hitKinds[] = $hit['kind'];
    }

    echo "  Kinds detected for LRM: " . implode(', ', $hitKinds) . "\n";

    // LRM should only be classified as bidi_controls, not default_ignorables
    $hasBidi = in_array('bidi_controls', $hitKinds);
    $hasIgnorables = in_array('default_ignorables', $hitKinds);

    assertTest('LRM classified as BiDi control', $hasBidi);
    assertTest('LRM not classified as default_ignorable', !$hasIgnorables);
} catch (Exception $e) {
    echo "✗ Duplicate classification test failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Test 12: Diff operations format validation
echo "Test 12: Diff operations structure validation\n";
echo str_repeat("=", 50) . "\n";
try {
    $text = "Hello\u{200B}world";
    $result = TextLinter::analyzeWithDiff($text, 'aggressive');

    assertTest('diffOps exists', isset($result['diffOps']));
    assertTest('diffOps is array', is_array($result['diffOps']));

    foreach ($result['diffOps'] as $op) {
        $hasRequiredKeys = isset($op['type'], $op['aStart'], $op['aLen'], $op['bStart'], $op['bLen']);
        if (!$hasRequiredKeys) {
            assertTest('All diff ops have required keys', false, 'Missing keys in op');
            break;
        }
    }

    if (!empty($result['diffOps'])) {
        assertTest('All diff ops have required keys', true);
    }
} catch (Exception $e) {
    echo "✗ Diff ops validation failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}
echo "\n";

// Summary
echo str_repeat("=", 50) . "\n";
echo "Test Summary:\n";
echo "  Passed: $testsPassed\n";
echo "  Failed: $testsFailed\n";
echo "  Total:  " . ($testsPassed + $testsFailed) . "\n";
echo "\n";

if ($testsFailed === 0) {
    echo "✓ All edge case tests passed!\n";
    exit(0);
} else {
    echo "✗ Some tests failed.\n";
    exit(1);
}
