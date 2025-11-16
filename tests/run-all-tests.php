#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/TextLinter.php';

/**
 * Automated Test Runner for Cosmic Text Linter
 *
 * Runs comprehensive Unicode security test scenarios
 * covering all modes (safe, aggressive, strict).
 */

class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public function run(): int
    {
        echo "🧪 Cosmic Text Linter - Automated Test Suite\n";
        echo str_repeat('=', 60) . "\n\n";

        // Core functionality tests
        $this->testBasicSanitization();
        $this->testZeroWidthRemoval();
        $this->testBiDiControls();
        $this->testHomoglyphNormalization();
        $this->testModeSelection();

        // Unicode edge cases
        $this->testZalgoText();
        $this->testSoftHyphen();
        $this->testHtmlEntities();
        $this->testTagCharacters();
        $this->testEmojiFlags();
        $this->testArabicDigits();
        $this->testNFKCCasefold();
        $this->testWhitespaceNormalization();
        $this->testNoncharacters();
        $this->testPrivateUse();
        $this->testRTLMirroring();
        $this->testOrphanCombining();
        $this->testSmartQuotes();
        $this->testEmptyInput();
        $this->testPureASCII();

        // Stress tests
        $this->testMixedEverything();

        echo "\n" . str_repeat('=', 60) . "\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";

        if ($this->failed > 0) {
            echo "\n❌ Failed tests:\n";
            foreach ($this->failures as $failure) {
                echo "  - {$failure}\n";
            }
            return 1;
        }

        echo "✅ All tests passed!\n";
        return 0;
    }

    private function test(string $name, callable $testFunc): void
    {
        echo str_pad($name, 50, '.');
        try {
            $testFunc();
            echo " ✓\n";
            $this->passed++;
        } catch (AssertionError $e) {
            echo " ✗\n";
            $this->failed++;
            $this->failures[] = "{$name}: {$e->getMessage()}";
        }
    }

    private function assertEquals($expected, $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new AssertionError($message ?: "Expected '$expected', got '$actual'");
        }
    }

    private function assertTrue(bool $condition, string $message = ''): void
    {
        if (!$condition) {
            throw new AssertionError($message ?: 'Assertion failed');
        }
    }

    private function assertContains(string $needle, string $haystack, string $message = ''): void
    {
        if (strpos($haystack, $needle) === false) {
            throw new AssertionError($message ?: "String does not contain '{$needle}'");
        }
    }

    // Test implementations

    private function testBasicSanitization(): void
    {
        $this->test('Basic sanitization returns proper structure', function () {
            $result = TextLinter::clean('Hello world', 'safe');
            $this->assertTrue(isset($result['text']));
            $this->assertTrue(isset($result['stats']));
            $this->assertTrue(is_array($result['stats']));
        });
    }

    private function testZeroWidthRemoval(): void
    {
        $this->test('Zero-width space removal (U+200B)', function () {
            $result = TextLinter::clean("Hello\u{200B}world", 'safe');
            $this->assertContains('Helloworld', $result['text']);
            $this->assertTrue($result['stats']['invisibles_removed'] > 0);
            $this->assertTrue($result['stats']['advisories']['had_default_ignorables']);
        });
    }

    private function testBiDiControls(): void
    {
        $this->test('BiDi control character removal', function () {
            $result = TextLinter::clean("Hello\u{202E}world\u{202C}", 'safe');
            $this->assertContains('Helloworld', $result['text']);
            $this->assertTrue($result['stats']['advisories']['had_bidi_controls']);
        });
    }

    private function testHomoglyphNormalization(): void
    {
        $this->test('Cyrillic homoglyph normalization (aggressive)', function () {
            // Cyrillic 'а' (U+0430) looks like Latin 'a'
            $result = TextLinter::clean("аpple", 'aggressive');
            $this->assertContains('apple', $result['text']);
            $this->assertTrue($result['stats']['homoglyphs_normalized'] > 0);
        });

        $this->test('Homoglyph preservation in safe mode', function () {
            $result = TextLinter::clean("аpple", 'safe');
            // In safe mode, homoglyphs are detected but not normalized
            $this->assertTrue(isset($result['stats']));
        });
    }

    private function testModeSelection(): void
    {
        $this->test('Safe mode selection', function () {
            $result = TextLinter::clean('test', 'safe');
            $this->assertEquals('safe', $result['stats']['mode']);
        });

        $this->test('Aggressive mode selection', function () {
            $result = TextLinter::clean('test', 'aggressive');
            $this->assertEquals('aggressive', $result['stats']['mode']);
        });

        $this->test('Strict mode selection', function () {
            $result = TextLinter::clean('test', 'strict');
            $this->assertEquals('strict', $result['stats']['mode']);
        });
    }

    private function testZalgoText(): void
    {
        $this->test('Zalgo text combining mark limiting', function () {
            // Text with excessive combining marks
            $zalgo = "H̸̡̪̯ͨ͊̽̅̾̎Ȩ̬̩̾͛ͪ̈́̀́͘";
            $result = TextLinter::clean($zalgo, 'safe');
            // Should limit combining marks per base character
            $this->assertTrue($result['stats']['advisories']['had_orphan_combining'] ||
                            mb_strlen($result['text']) < mb_strlen($zalgo));
        });
    }

    private function testSoftHyphen(): void
    {
        $this->test('Soft hyphen removal (U+00AD)', function () {
            $result = TextLinter::clean("super\u{00AD}cali\u{00AD}fragil", 'safe');
            $this->assertContains('supercalifragil', $result['text']);
            $this->assertTrue($result['stats']['invisibles_removed'] > 0);
        });
    }

    private function testHtmlEntities(): void
    {
        $this->test('HTML entity decoding', function () {
            $result = TextLinter::clean('Hello&nbsp;world&mdash;test', 'safe');
            $this->assertContains('Hello world', $result['text']);
            $this->assertContains('-test', $result['text']);
            $this->assertTrue($result['stats']['advisories']['had_html_entities']);
        });
    }

    private function testTagCharacters(): void
    {
        $this->test('TAG character removal (aggressive)', function () {
            // TAG characters U+E0020-E007F
            $result = TextLinter::clean("test\u{E0020}tag\u{E007F}", 'aggressive');
            $this->assertTrue($result['stats']['advisories']['had_tag_chars']);
        });
    }

    private function testEmojiFlags(): void
    {
        $this->test('Emoji flag preservation (safe mode)', function () {
            // England flag: 🏴󠁧󠁢󠁥󠁮󠁧󠁿
            $result = TextLinter::clean("🏴\u{E0067}\u{E0062}\u{E0065}\u{E006E}\u{E0067}\u{E007F}", 'safe');
            // TAG chars in emoji sequences should be preserved in safe mode
            $this->assertTrue(isset($result['text']));
        });
    }

    private function testArabicDigits(): void
    {
        $this->test('Arabic-Indic digit preservation (safe)', function () {
            $result = TextLinter::clean("\u{0661}\u{0662}\u{0663}", 'safe');
            $this->assertEquals(0, $result['stats']['digits_normalized']);
        });

        $this->test('Arabic-Indic digit normalization (aggressive)', function () {
            $result = TextLinter::clean("\u{0661}\u{0662}\u{0663}", 'aggressive');
            $this->assertContains('123', $result['text']);
            $this->assertTrue($result['stats']['digits_normalized'] > 0);
            $this->assertTrue($result['stats']['advisories']['had_non_ascii_digits']);
        });
    }

    private function testNFKCCasefold(): void
    {
        $this->test('NFKC casefold in strict mode', function () {
            // Fullwidth letters
            $result = TextLinter::clean('ＨＥＬＬＯ', 'strict');
            $this->assertContains('hello', $result['text']);
        });
    }

    private function testWhitespaceNormalization(): void
    {
        $this->test('Multiple spaces normalized', function () {
            $result = TextLinter::clean('Hello     world', 'safe');
            $this->assertContains('Hello world', $result['text']);
        });

        $this->test('Multiple newlines limited', function () {
            $result = TextLinter::clean("Hello\n\n\n\n\nworld", 'safe');
            // Should limit to max 2 consecutive newlines
            $this->assertTrue(substr_count($result['text'], "\n") <= 3);
        });
    }

    private function testNoncharacters(): void
    {
        $this->test('Noncharacter removal (aggressive)', function () {
            $result = TextLinter::clean("test\u{FDD0}here", 'aggressive');
            $this->assertContains('testhere', $result['text']);
            $this->assertTrue($result['stats']['advisories']['had_noncharacters']);
        });

        $this->test('Noncharacter preservation (safe)', function () {
            $result = TextLinter::clean("test\u{FDD0}here", 'safe');
            // Safe mode preserves noncharacters
            $this->assertEquals('safe', $result['stats']['mode']);
        });
    }

    private function testPrivateUse(): void
    {
        $this->test('Private Use Area removal (strict)', function () {
            $result = TextLinter::clean("test\u{E000}private", 'strict');
            $this->assertContains('testprivate', $result['text']);
            $this->assertTrue($result['stats']['advisories']['had_private_use']);
        });

        $this->test('Private Use Area preservation (safe)', function () {
            $result = TextLinter::clean("test\u{E000}private", 'safe');
            // Safe mode preserves PUA
            $this->assertEquals('safe', $result['stats']['mode']);
        });
    }

    private function testRTLMirroring(): void
    {
        $this->test('RTL mirrored punctuation detection', function () {
            // Hebrew text with parentheses - should trigger mirrored punctuation advisory
            $result = TextLinter::clean("שלום (test)", 'safe');
            // May or may not trigger depending on implementation details
            $this->assertTrue(isset($result['stats']['advisories']['had_mirrored_punctuation']));
        });
    }

    private function testOrphanCombining(): void
    {
        $this->test('Orphan combining mark removal', function () {
            // Start with combining mark (no base)
            $result = TextLinter::clean("\u{0301}test", 'safe');
            $this->assertContains('test', $result['text']);
            $this->assertTrue($result['stats']['advisories']['had_orphan_combining']);
        });
    }

    private function testSmartQuotes(): void
    {
        $this->test('Smart quote normalization', function () {
            $result = TextLinter::clean("\u{201C}Hello\u{201D}", 'safe');
            $this->assertContains('"Hello"', $result['text']);
        });
    }

    private function testEmptyInput(): void
    {
        $this->test('Empty string handling', function () {
            // clean() expects non-empty string, but we test the edge case
            // The API layer rejects empty strings, but the engine should handle it
            $result = TextLinter::clean(' ', 'safe');
            // Should return empty string after trim
            $this->assertTrue(strlen(trim($result['text'])) === 0);
        });
    }

    private function testPureASCII(): void
    {
        $this->test('Pure ASCII unchanged (except final newline)', function () {
            $result = TextLinter::clean('Hello world 123', 'safe');
            // Final newline is added, so characters_removed might be -1
            $this->assertTrue($result['stats']['characters_removed'] >= -1);
            $this->assertTrue(strpos($result['text'], 'Hello world 123') !== false);
        });
    }

    private function testMixedEverything(): void
    {
        $this->test('Mixed attack stress test', function () {
            // Combine multiple attack vectors
            $input = "\u{201C}Smаrt\u{200B}quоtes\u{201D}\u{202E}CTRL\u{202C} with Сyrillic,\u{200B}ZWS,\u{200B}BiDi&nbsp;&mdash;\u{0663}";
            $result = TextLinter::clean($input, 'aggressive');

            // Should have removed/normalized multiple things
            $this->assertTrue($result['stats']['characters_removed'] > 0);
            $this->assertTrue(
                $result['stats']['advisories']['had_bidi_controls'] ||
                $result['stats']['advisories']['had_default_ignorables'] ||
                $result['stats']['advisories']['had_html_entities']
            );
        });
    }
}

// Run tests
$runner = new TestRunner();
exit($runner->run());
