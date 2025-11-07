<?php
/**
 * Test markdown preservation and AI watermark removal
 */

require_once __DIR__ . '/api/TextLinter.php';

echo "Testing Markdown Preservation and AI Watermark Removal\n";
echo "=======================================================\n\n";

// Test 1: Markdown should be preserved in safe mode
echo "Test 1: Markdown preservation in safe mode\n";
$markdown = "This is *bold* and _italic_ text with **strong** emphasis.";
$result_safe = TextLinter::clean($markdown, 'safe');
$result_aggressive = TextLinter::clean($markdown, 'aggressive');
echo "Input: $markdown\n";
echo "Safe mode output: " . $result_safe['text'];
echo "Aggressive mode output: " . $result_aggressive['text'];
echo "\n";

// Test 2: AI watermark with variation selectors (invisible)
echo "Test 2: AI watermark removal (variation selectors)\n";
$watermarked = "This\u{FE0F}is\u{FE0E}watermarked\u{FE0F}text";
echo "Input (hex): " . bin2hex($watermarked) . "\n";
$result_safe_wm = TextLinter::clean($watermarked, 'safe');
$result_agg_wm = TextLinter::clean($watermarked, 'aggressive');
echo "Safe mode removed: " . $result_safe_wm['stats']['invisibles_removed'] . " invisibles\n";
echo "Aggressive mode removed: " . $result_agg_wm['stats']['invisibles_removed'] . " invisibles\n";
echo "Output: " . $result_agg_wm['text'];
echo "\n";

// Test 3: Unicode spaces at end of sentences (should be normalized)
echo "Test 3: Unicode spaces at sentence end\n";
$spaced = "Hello world.\u{2003}This is a test.\u{3000}Another sentence.";
echo "Input (hex): " . bin2hex($spaced) . "\n";
$result_spaced = TextLinter::clean($spaced, 'safe');
echo "Output: " . $result_spaced['text'];
echo "Characters removed: " . $result_spaced['stats']['characters_removed'] . "\n";
echo "\n";

// Test 4: Code blocks with backticks
echo "Test 4: Code blocks (backticks should be preserved)\n";
$code = "Use `console.log()` for debugging or ```javascript code blocks```.";
$result_code = TextLinter::clean($code, 'safe');
echo "Input: $code\n";
echo "Output: " . $result_code['text'];
echo "\n";

// Test 5: Real AI watermark scenario
echo "Test 5: Real AI watermark scenario\n";
$ai_text = "The\u{FE0F} quick\u{FE0E} brown\u{FE0F} fox\u{2003}jumps\u{3000}over.";
echo "Input (hex): " . bin2hex($ai_text) . "\n";
$result_ai = TextLinter::clean($ai_text, 'aggressive');
echo "Invisibles removed: " . $result_ai['stats']['invisibles_removed'] . "\n";
echo "Output: " . $result_ai['text'];
echo "\n";

echo "All tests completed!\n";
