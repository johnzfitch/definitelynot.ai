<?php
/**
 * Test script for invisible space logic fixes
 */

require_once __DIR__ . '/api/TextLinter.php';

echo "Testing Invisible Space Logic Fixes\n";
echo "====================================\n\n";

// Test 1: Various Unicode spaces
echo "Test 1: Unicode spaces (should be removed in aggressive/strict)\n";
$test1 = "Hello\u{2003}World\u{3000}Test";
echo "Input: " . bin2hex($test1) . "\n";
$result1_safe = TextLinter::clean($test1, 'safe');
$result1_aggressive = TextLinter::clean($test1, 'aggressive');
echo "Safe mode removed: " . $result1_safe['stats']['invisibles_removed'] . " chars\n";
echo "Aggressive mode removed: " . $result1_aggressive['stats']['invisibles_removed'] . " chars\n";
echo "Output (aggressive): " . $result1_aggressive['text'] . "\n\n";

// Test 2: Zero-width joiners (should preserve in safe mode)
echo "Test 2: Zero-width joiners (preserved in safe mode)\n";
$test2 = "Test\u{200D}Text";
echo "Input: " . bin2hex($test2) . "\n";
$result2_safe = TextLinter::clean($test2, 'safe');
$result2_aggressive = TextLinter::clean($test2, 'aggressive');
echo "Safe mode removed: " . $result2_safe['stats']['invisibles_removed'] . " chars\n";
echo "Aggressive mode removed: " . $result2_aggressive['stats']['invisibles_removed'] . " chars\n";
echo "Output (safe): " . bin2hex($result2_safe['text']) . "\n";
echo "Output (aggressive): " . bin2hex($result2_aggressive['text']) . "\n\n";

// Test 3: Variation selectors with emoji
echo "Test 3: Variation selectors (FE0F removed in aggressive)\n";
$test3 = "😀\u{FE0F}";
echo "Input: " . bin2hex($test3) . "\n";
$result3_safe = TextLinter::clean($test3, 'safe');
$result3_aggressive = TextLinter::clean($test3, 'aggressive');
echo "Safe mode removed: " . $result3_safe['stats']['invisibles_removed'] . " chars\n";
echo "Aggressive mode removed: " . $result3_aggressive['stats']['invisibles_removed'] . " chars\n\n";

// Test 4: Ogham space (new detection)
echo "Test 4: Ogham space (new detection in aggressive mode)\n";
$test4 = "Hello\u{1680}World";
echo "Input: " . bin2hex($test4) . "\n";
$result4_safe = TextLinter::clean($test4, 'safe');
$result4_aggressive = TextLinter::clean($test4, 'aggressive');
echo "Safe mode removed: " . $result4_safe['stats']['invisibles_removed'] . " chars\n";
echo "Aggressive mode removed: " . $result4_aggressive['stats']['invisibles_removed'] . " chars\n";
echo "Output (safe): " . $result4_safe['text'] . "\n";
echo "Output (aggressive): " . $result4_aggressive['text'] . "\n\n";

// Test 5: Mathematical space (new detection)
echo "Test 5: Mathematical space (new detection in aggressive mode)\n";
$test5 = "2\u{205F}+\u{205F}2";
echo "Input: " . bin2hex($test5) . "\n";
$result5_safe = TextLinter::clean($test5, 'safe');
$result5_aggressive = TextLinter::clean($test5, 'aggressive');
echo "Safe mode removed: " . $result5_safe['stats']['invisibles_removed'] . " chars\n";
echo "Aggressive mode removed: " . $result5_aggressive['stats']['invisibles_removed'] . " chars\n";
echo "Output (safe): " . $result5_safe['text'] . "\n";
echo "Output (aggressive): " . $result5_aggressive['text'] . "\n\n";

echo "All tests completed!\n";
