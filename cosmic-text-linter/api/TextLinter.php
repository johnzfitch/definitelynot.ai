<?php

declare(strict_types=1);

/**
 * Cosmic Text Linter Core Engine v2.2.1
 *
 * Implements a comprehensive 16-step Unicode security sanitization pipeline that
 * defends against various Unicode-based attacks including:
 *
 * - Trojan Source (CVE-2021-42574) - BiDi text attacks
 * - Homoglyph spoofing - Visually similar characters from different scripts
 * - Invisible character steganography - Zero-width and format characters
 * - TAG block injection - Prompt injection via TAG characters
 * - Zalgo text - Excessive combining marks
 * - Mixed script confusion - Script mixing attacks
 * - Digit spoofing - Non-ASCII digit confusion
 * - Noncharacter exploitation - Reserved code point abuse
 * - Private Use Area covert channels - Hidden data transmission
 *
 * The sanitization process is mode-aware, offering three security profiles:
 *
 * - **Safe**: Preserves multilingual content, emoji, RTL marks (detection-focused)
 * - **Aggressive**: Latin-preferred, strips risky formatting (moderation-focused)
 * - **Strict**: Maximum security, ASCII-preferred (security-critical contexts)
 *
 * @package CosmicTextLinter
 * @version 2.2.1
 * @author Internet Universe
 * @see https://unicode.org/reports/tr39/ Unicode Security Mechanisms (UTS #39)
 * @see https://unicode.org/reports/tr9/ Unicode Bidirectional Algorithm (UAX #9)
 */
class TextLinter
{
    /** @var int Maximum allowed input size in bytes (1 MB) */
    public const MAX_INPUT_SIZE = 1048576;

    /** @var string Current version of the TextLinter engine */
    public const VERSION = '2.2.1';

    /**
     * Execute the complete 16-step Unicode security sanitization pipeline.
     *
     * This is the main entry point for text sanitization. The method processes
     * input text through multiple security-focused transformations, tracking
     * statistics and advisory flags for detected threats.
     *
     * Pipeline Execution Order:
     * 1. HTML entity decoding
     * 2. ASCII control character removal
     * 3. BiDi control character removal (Trojan Source defense)
     * 4. Unicode normalization (NFC or NFKC+casefold)
     * 5. Invisible character removal (two passes)
     * 6. Whitespace normalization
     * 7. Digit normalization (non-ASCII to ASCII)
     * 8. Punctuation normalization (smart quotes, etc.)
     * 9. Combining mark cleanup (Zalgo defense)
     * 10. Formatting removal (markdown, bullets)
     * 11. Noncharacter removal
     * 12. Private Use Area removal (strict mode only)
     * 13. TAG block removal (preserves emoji flags)
     * 14. Homoglyph normalization (aggressive/strict)
     * 15. Spoof audit (ICU Spoofchecker)
     * 16. Mirrored punctuation detection
     * 17. Final cleanup (paragraph detection, whitespace)
     *
     * @param string $text Raw input text (must be UTF-8 encoded)
     * @param string $mode Operation mode: 'safe', 'aggressive', or 'strict'
     *                     Defaults to 'safe' if invalid value provided
     *
     * @return array{text:string,stats:array<string,mixed>} Sanitization result
     *         - text: Sanitized output text with trailing newline
     *         - stats: Comprehensive statistics array containing:
     *           - original_length: Input character count
     *           - final_length: Output character count
     *           - characters_removed: Difference between original and final
     *           - mode: Operation mode used
     *           - invisibles_removed: Count of invisible characters removed
     *           - homoglyphs_normalized: Count of homoglyphs converted
     *           - digits_normalized: Count of non-ASCII digits converted
     *           - advisories: Array of security detection flags (12 boolean flags)
     *
     * @throws RuntimeException If PCRE regex operations fail
     *
     * @example
     * // Basic sanitization (safe mode)
     * $result = TextLinter::clean("Hello\u{200B}world");
     * // Returns: ['text' => "Helloworld\n", 'stats' => [...]]
     *
     * @example
     * // Aggressive mode for user-generated content
     * $result = TextLinter::clean("Hеllο", 'aggressive'); // Cyrillic е, Greek ο
     * // Returns: ['text' => "Hello\n", 'stats' => [...]]
     *
     * @example
     * // Strict mode for code identifiers
     * $result = TextLinter::clean("Café №123", 'strict');
     * // Returns: ['text' => "cafe no123\n", 'stats' => [...]]
     *
     * @since 2.0.0
     */
    public static function clean(string $text, string $mode = 'safe'): array
    {
        $mode = in_array($mode, ['safe', 'aggressive', 'strict'], true) ? $mode : 'safe';

        $stats = [
            'original_length' => mb_strlen($text, 'UTF-8'),
            'final_length' => null,
            'characters_removed' => 0,
            'mode' => $mode,
            'invisibles_removed' => 0,
            'homoglyphs_normalized' => 0,
            'digits_normalized' => 0,
            'advisories' => [
                'had_bidi_controls' => false,
                'had_mixed_scripts' => false,
                'had_default_ignorables' => false,
                'had_tag_chars' => false,
                'had_orphan_combining' => false,
                'confusable_suspected' => false,
                'had_html_entities' => false,
                'had_ascii_controls' => false,
                'had_noncharacters' => false,
                'had_private_use' => false,
                'had_mirrored_punctuation' => false,
                'had_non_ascii_digits' => false,
            ],
        ];

        $text = self::decodeEntities($text, $stats);
        $text = self::stripControls($text, $stats);
        $text = self::stripBidiControls($text, $stats);
        $text = $mode === 'strict'
            ? self::nfkcCasefold($text)
            : self::nfcNormalize($text);
        $text = self::stripInvisibles($text, $stats, $mode);
        $text = self::normalizeWhitespace($text);
        $text = self::asciiDigits($text, $stats, $mode);
        $text = self::normalizePunctuation($text);
        $text = self::cleanOrphanCombining($text, $stats);
        $text = self::stripFormatting($text);
        $text = self::stripNoncharacters($text, $stats, $mode);
        $text = self::stripPrivateUse($text, $stats, $mode);
        $text = self::stripTagBlock($text, $stats, $mode);
        $text = self::normalizeHomoglyphs($text, $stats, $mode);
        $text = self::spoofAudit($text, $stats, $mode);
        $text = self::detectMirroredPunctuation($text, $stats);
        $text = self::stripInvisibles($text, $stats, $mode); // second pass
        $text = self::finalCleanup($text);

        $stats['final_length'] = mb_strlen($text, 'UTF-8');
        $stats['characters_removed'] = $stats['original_length'] - $stats['final_length'];

        return [
            'text' => $text,
            'stats' => $stats,
        ];
    }

    /**
     * Step 1: Decode HTML entities to their Unicode equivalents.
     *
     * Converts HTML entities like &nbsp;, &mdash;, &lt; to their actual Unicode
     * characters. This prevents entity-encoded obfuscation attacks where malicious
     * content is hidden behind HTML encoding.
     *
     * @param string $text Input text that may contain HTML entities
     * @param array<string,mixed> $stats Statistics array (modified by reference)
     *
     * @return string Text with all HTML entities decoded to Unicode
     *
     * @internal Pipeline step 1 of 16
     * @since 2.0.0
     */
    private static function decodeEntities(string $text, array &$stats): string
    {
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        if ($decoded !== $text) {
            $stats['advisories']['had_html_entities'] = true;
        }
        return $decoded;
    }

    /**
     * Step 2: Remove ASCII control characters (C0 and C1 controls).
     *
     * Strips dangerous control characters from the C0 (U+0000-U+001F) and C1
     * (U+007F-U+009F) ranges, except for TAB (U+0009) and LF (U+000A) which
     * are preserved for formatting. Control characters can cause:
     * - Terminal injection attacks
     * - Log injection
     * - Protocol confusion
     * - Rendering corruption
     *
     * Preserved characters: TAB (0x09), LF (0x0A)
     * Removed characters: NUL, SOH, STX, ETX, EOT, ENQ, ACK, BEL, BS, VT, FF,
     *                     CR, SO, SI, DLE, DC1-4, NAK, SYN, ETB, CAN, EM, SUB,
     *                     ESC, FS, GS, RS, US, DEL, and C1 controls
     *
     * @param string $text Input text
     * @param array<string,mixed> $stats Statistics array (modified by reference)
     *
     * @return string Text with control characters removed
     *
     * @throws RuntimeException If PCRE regex fails
     *
     * @internal Pipeline step 2 of 16
     * @since 2.0.0
     */
    private static function stripControls(string $text, array &$stats): string
    {
        $pattern = '/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}]+/u';
        $clean = preg_replace($pattern, '', $text);
        if ($clean === null) {
            throw new RuntimeException('PCRE error in stripControls');
        }
        if ($clean !== $text) {
            $stats['advisories']['had_ascii_controls'] = true;
        }
        return $clean;
    }

    /**
     * Step 3: Remove Bidirectional text control characters (Trojan Source defense).
     *
     * Strips all BiDi formatting characters that can be exploited in Trojan Source
     * attacks (CVE-2021-42574). These characters can make code appear to execute
     * differently than it actually does by reordering text visually.
     *
     * Removed BiDi controls:
     * - U+202A (LRE) - Left-to-Right Embedding
     * - U+202B (RLE) - Right-to-Left Embedding
     * - U+202C (PDF) - Pop Directional Formatting
     * - U+202D (LRO) - Left-to-Right Override
     * - U+202E (RLO) - Right-to-Left Override
     * - U+2066 (LRI) - Left-to-Right Isolate
     * - U+2067 (RLI) - Right-to-Left Isolate
     * - U+2068 (FSI) - First Strong Isolate
     * - U+2069 (PDI) - Pop Directional Isolate
     *
     * Note: Legitimate RTL marks (LRM U+200E, RLM U+200F) are preserved in safe
     * mode and removed in aggressive/strict modes (handled in stripInvisibles).
     *
     * @param string $text Input text
     * @param array<string,mixed> $stats Statistics array (modified by reference)
     *
     * @return string Text with BiDi controls removed
     *
     * @throws RuntimeException If PCRE regex fails
     *
     * @see https://cve.mitre.org/cgi-bin/cvename.cgi?name=CVE-2021-42574
     * @see https://trojansource.codes/
     *
     * @internal Pipeline step 3 of 16
     * @since 2.0.0
     */
    private static function stripBidiControls(string $text, array &$stats): string
    {
        $pattern = '/[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';
        $clean = preg_replace($pattern, '', $text);
        if ($clean === null) {
            throw new RuntimeException('PCRE error in stripBidiControls');
        }
        if ($clean !== $text) {
            $stats['advisories']['had_bidi_controls'] = true;
        }
        return $clean;
    }

    /** Step 4a: NFC normalization (safe & aggressive) */
    private static function nfcNormalize(string $text): string
    {
        if (class_exists('Normalizer')) {
            $normalized = Normalizer::normalize($text, Normalizer::FORM_C);
            if ($normalized !== false) {
                return $normalized;
            }
        }
        return $text;
    }

    /** Step 4b: NFKC casefold (strict) */
    private static function nfkcCasefold(string $text): string
    {
        if (class_exists('Transliterator')) {
            $trans = Transliterator::create('NFKC; Lower');
            if ($trans) {
                $result = $trans->transliterate($text);
                if ($result !== false) {
                    return $result;
                }
            }
        }
        if (class_exists('Normalizer')) {
            $normalized = Normalizer::normalize($text, Normalizer::FORM_KC);
            if ($normalized !== false) {
                $text = $normalized;
            }
        }
        return mb_strtolower($text, 'UTF-8');
    }

    /** Step 5: Strip invisible characters with mode awareness */
    private static function stripInvisibles(string $text, array &$stats, string $mode): string
    {
        $invisibles = [
            '\x{200B}', '\x{200C}', '\x{200D}',
            '\x{2028}', '\x{2029}', '\x{2060}',
            '\x{2061}', '\x{2062}', '\x{2063}', '\x{2064}',
            '\x{206A}', '\x{206B}', '\x{206C}', '\x{206D}', '\x{206E}', '\x{206F}',
            '\x{FEFF}', '\x{FFF9}', '\x{FFFA}', '\x{FFFB}',
            '\x{00AD}', '\x{180E}',
        ];

        if ($mode === 'safe') {
            $safeSet = array_flip(['\x{200C}', '\x{200D}']);
            $invisibles = array_values(array_filter($invisibles, function ($item) use ($safeSet) {
                return !isset($safeSet[$item]);
            }));
        }

        if ($mode !== 'safe') {
            $invisibles[] = '\x{200E}';
            $invisibles[] = '\x{200F}';
            $invisibles[] = '\x{061C}';
        }

        for ($i = 0xFE00; $i <= 0xFE0E; $i++) {
            $invisibles[] = '\x{' . strtoupper(dechex($i)) . '}';
        }

        if ($mode === 'aggressive') {
            $invisibles[] = '\x{FE0F}';
            $invisibles[] = '\x{180B}';
            $invisibles[] = '\x{180C}';
            $invisibles[] = '\x{180D}';
            for ($i = 0xE0100; $i <= 0xE01EF; $i++) {
                $invisibles[] = '\x{' . strtoupper(dechex($i)) . '}';
            }
        }

        $pattern = '/[' . implode('', $invisibles) . ']/u';
        $clean = preg_replace($pattern, '', $text);
        if ($clean === null) {
            throw new RuntimeException('PCRE error in stripInvisibles');
        }
        if ($clean !== $text) {
            $stats['advisories']['had_default_ignorables'] = true;
            $stats['invisibles_removed'] += mb_strlen($text, 'UTF-8') - mb_strlen($clean, 'UTF-8');
        }
        return $clean;
    }

    /** Step 6: Whitespace normalization */
    private static function normalizeWhitespace(string $text): string
    {
        $whitespace = [
            '\x{00A0}', '\x{1680}', '\x{2000}', '\x{2001}', '\x{2002}', '\x{2003}',
            '\x{2004}', '\x{2005}', '\x{2006}', '\x{2007}', '\x{2008}', '\x{2009}',
            '\x{200A}', '\x{202F}', '\x{205F}', '\x{3000}', '\t',
        ];
        $pattern = '/[' . implode('', $whitespace) . ']/u';
        $clean = preg_replace($pattern, ' ', $text);
        if ($clean === null) {
            throw new RuntimeException('PCRE error in normalizeWhitespace');
        }
        $clean = str_replace(["\r\n", "\r"], "\n", $clean);
        $clean = preg_replace('/ +/', ' ', $clean);
        $clean = preg_replace('/\n{3,}/', "\n\n", $clean);
        return $clean ?? '';
    }

    /** Step 7: ASCII digit normalization */
    private static function asciiDigits(string $text, array &$stats, string $mode): string
    {
        if ($mode === 'safe') {
            return $text;
        }
        $buffer = '';
        $length = mb_strlen($text, 'UTF-8');
        $normalized = 0;
        for ($i = 0; $i < $length; $i++) {
            $ch = mb_substr($text, $i, 1, 'UTF-8');
            $digit = null;
            if (class_exists('IntlChar')) {
                $cp = mb_ord($ch, 'UTF-8');
                if ($cp !== false) {
                    $digit = IntlChar::charDigitValue($cp);
                    if (!is_int($digit) || $digit < 0 || $digit > 9) {
                        $digit = null;
                    }
                }
            }
            if ($digit === null) {
                $cp = mb_ord($ch, 'UTF-8');
                if ($cp >= 0x0660 && $cp <= 0x0669) {
                    $digit = $cp - 0x0660;
                } elseif ($cp >= 0x06F0 && $cp <= 0x06F9) {
                    $digit = $cp - 0x06F0;
                }
            }
            if ($digit !== null && $digit >= 0 && $digit <= 9) {
                $buffer .= (string) $digit;
                $normalized++;
            } else {
                $buffer .= $ch;
            }
        }
        if ($normalized > 0) {
            $stats['digits_normalized'] += $normalized;
            $stats['advisories']['had_non_ascii_digits'] = true;
        }
        return $buffer;
    }

    /** Step 8: Smart punctuation normalization */
    private static function normalizePunctuation(string $text): string
    {
        $map = [
            '“' => '"', '”' => '"', '„' => '"', '‟' => '"',
            '‘' => '\'', '’' => '\'', '‚' => '\'', '‛' => '\'',
            '—' => '-', '–' => '-', '―' => '-', '‐' => '-', '‑' => '-', '‒' => '-', '−' => '-',
            '…' => '...',
            '«' => '"', '»' => '"', '‹' => '\'', '›' => '\'',
            '′' => '\'', '″' => '"', '‴' => "'''",
        ];
        return strtr($text, $map);
    }

    /** Step 9: Remove orphan combining marks & limit Zalgo */
    private static function cleanOrphanCombining(string $text, array &$stats): string
    {
        $before = $text;
        $text = preg_replace('/^\p{M}+/u', '', $text);
        $text = preg_replace('/(\s)\p{M}+/u', '$1', $text);
        $text = preg_replace('/(?<=\n)\p{M}+/u', '', $text);
        $text = preg_replace('/(\P{M})(\p{M}{2})\p{M}+/u', '$1$2', $text);
        if ($text === null) {
            throw new RuntimeException('PCRE error in cleanOrphanCombining');
        }
        if ($text !== $before) {
            $stats['advisories']['had_orphan_combining'] = true;
        }
        return $text ?? '';
    }

    /** Step 10: Strip markdown/HTML formatting */
    private static function stripFormatting(string $text): string
    {
        $bullets = ['•', '◦', '▪', '‣', '⁃', '⁌', '⁍', '∙', '○', '●', '◘', '◙'];
        $text = str_replace($bullets, '-', $text);
        $text = strip_tags($text);
        $text = preg_replace('/\*([^*\n]{1,50})\*/u', '$1', $text);
        $text = preg_replace('/_([^_\n]{1,50})_/u', '$1', $text);
        return $text ?? '';
    }

    /** Step 11: Strip noncharacters */
    private static function stripNoncharacters(string $text, array &$stats, string $mode): string
    {
        if ($mode === 'safe') {
            return $text;
        }
        $before = $text;
        $text = preg_replace('/[\x{FDD0}-\x{FDEF}]/u', '', $text);
        if ($text === null) {
            throw new RuntimeException('PCRE error in stripNoncharacters');
        }
        $ranges = [];
        for ($plane = 0; $plane <= 0x10; $plane++) {
            $ranges[] = '\x{' . strtoupper(dechex(($plane << 16) | 0xFFFE)) . '}';
            $ranges[] = '\x{' . strtoupper(dechex(($plane << 16) | 0xFFFF)) . '}';
        }
        $pattern = '/[' . implode('', $ranges) . ']/u';
        $text = preg_replace($pattern, '', $text);
        if ($text === null) {
            throw new RuntimeException('PCRE error in stripNoncharacters planes');
        }
        if ($text !== $before) {
            $stats['advisories']['had_noncharacters'] = true;
        }
        return $text ?? '';
    }

    /** Step 12: Strip Private Use Area (strict mode) */
    private static function stripPrivateUse(string $text, array &$stats, string $mode): string
    {
        if ($mode !== 'strict') {
            return $text;
        }
        $before = $text;
        $text = preg_replace('/[\x{E000}-\x{F8FF}]/u', '', $text);
        $text = preg_replace('/[\x{F0000}-\x{FFFFD}]/u', '', $text);
        $text = preg_replace('/[\x{100000}-\x{10FFFD}]/u', '', $text);
        if ($text === null) {
            throw new RuntimeException('PCRE error in stripPrivateUse');
        }
        if ($text !== $before) {
            $stats['advisories']['had_private_use'] = true;
        }
        return $text ?? '';
    }

    /** Step 13: TAG block removal with emoji flag preservation */
    private static function stripTagBlock(string $text, array &$stats, string $mode): string
    {
        $before = $text;
        $protected = [];
        if ($mode !== 'aggressive') {
            $text = preg_replace_callback(
                '/\x{1F3F4}[\x{E0020}-\x{E007E}]+\x{E007F}/u',
                function ($match) use (&$protected) {
                    $token = "\x01" . count($protected) . "\x02";
                    $protected[$token] = $match[0];
                    return $token;
                },
                $text
            );
        }
        $text = preg_replace('/[\x{E0000}-\x{E007F}]+/u', '', $text);
        if ($text === null) {
            throw new RuntimeException('PCRE error in stripTagBlock');
        }
        if (!empty($protected)) {
            $text = strtr($text, $protected);
        }
        if ($text !== $before) {
            $stats['advisories']['had_tag_chars'] = true;
        }
        return $text ?? '';
    }

    /** Step 14: Homoglyph normalization (aggressive & strict) */
    private static function normalizeHomoglyphs(string $text, array &$stats, string $mode): string
    {
        if ($mode === 'safe') {
            return $text;
        }
        $map = [
            'а' => 'a', 'е' => 'e', 'о' => 'o', 'р' => 'p', 'с' => 'c', 'у' => 'y', 'х' => 'x', 'ѕ' => 's', 'і' => 'i', 'ј' => 'j', 'ԁ' => 'd', 'ԛ' => 'q', 'ѵ' => 'v', 'һ' => 'h', 'ҏ' => 'p',
            'А' => 'A', 'В' => 'B', 'Е' => 'E', 'К' => 'K', 'М' => 'M', 'Н' => 'H', 'О' => 'O', 'Р' => 'P', 'С' => 'C', 'Т' => 'T', 'Х' => 'X', 'Ѕ' => 'S', 'І' => 'I', 'Ј' => 'J',
            'α' => 'a', 'β' => 'b', 'γ' => 'y', 'ε' => 'e', 'ι' => 'i', 'ο' => 'o', 'ρ' => 'p', 'υ' => 'u', 'ω' => 'w', 'ν' => 'v', 'τ' => 't',
            'Α' => 'A', 'Β' => 'B', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'H', 'Ι' => 'I', 'Κ' => 'K', 'Μ' => 'M', 'Ν' => 'N', 'Ο' => 'O', 'Ρ' => 'P', 'Τ' => 'T', 'Υ' => 'Y', 'Χ' => 'X',
            'Ａ' => 'A', 'Ｂ' => 'B', 'Ｃ' => 'C', 'Ｄ' => 'D', 'Ｅ' => 'E', 'Ｆ' => 'F', 'Ｇ' => 'G', 'Ｈ' => 'H', 'Ｉ' => 'I', 'Ｊ' => 'J', 'Ｋ' => 'K', 'Ｌ' => 'L', 'Ｍ' => 'M', 'Ｎ' => 'N', 'Ｏ' => 'O', 'Ｐ' => 'P', 'Ｑ' => 'Q', 'Ｒ' => 'R', 'Ｓ' => 'S', 'Ｔ' => 'T', 'Ｕ' => 'U', 'Ｖ' => 'V', 'Ｗ' => 'W', 'Ｘ' => 'X', 'Ｙ' => 'Y', 'Ｚ' => 'Z',
            'ａ' => 'a', 'ｂ' => 'b', 'ｃ' => 'c', 'ｄ' => 'd', 'ｅ' => 'e', 'ｆ' => 'f', 'ｇ' => 'g', 'ｈ' => 'h', 'ｉ' => 'i', 'ｊ' => 'j', 'ｋ' => 'k', 'ｌ' => 'l', 'ｍ' => 'm', 'ｎ' => 'n', 'ｏ' => 'o', 'ｐ' => 'p', 'ｑ' => 'q', 'ｒ' => 'r', 'ｓ' => 's', 'ｔ' => 't', 'ｕ' => 'u', 'ｖ' => 'v', 'ｗ' => 'w', 'ｘ' => 'x', 'ｙ' => 'y', 'ｚ' => 'z',
            '０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4', '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
        ];
        $pattern = '/[' . implode('', array_map(function ($char) {
            return preg_quote($char, '/');
        }, array_keys($map))) . ']/u';
        $matches = [];
        $count = preg_match_all($pattern, $text, $matches);
        $normalized = strtr($text, $map);
        if ($count > 0) {
            $stats['homoglyphs_normalized'] += (int) $count;
        }
        return $normalized;
    }

    /** Step 15: Spoof audit using Spoofchecker */
    private static function spoofAudit(string $text, array &$stats, string $mode): string
    {
        if (!class_exists('Spoofchecker')) {
            return $text;
        }
        try {
            $checker = new Spoofchecker();
            $checker->setChecks(
                Spoofchecker::SINGLE_SCRIPT_CONFUSABLE |
                Spoofchecker::MIXED_SCRIPT_CONFUSABLE |
                Spoofchecker::WHOLE_SCRIPT_CONFUSABLE |
                Spoofchecker::INVISIBLE
            );
            if ($checker->isSuspicious($text)) {
                $stats['advisories']['confusable_suspected'] = true;
                if ($mode === 'safe') {
                    $stats['advisories']['had_mixed_scripts'] = true;
                }
            }
        } catch (Throwable $e) {
            // Spoofchecker may not be available; fail closed by ignoring.
        }
        return $text;
    }

    /** Step 16: Detect mirrored punctuation when RTL context present */
    private static function detectMirroredPunctuation(string $text, array &$stats): string
    {
        if (!class_exists('IntlChar')) {
            return $text;
        }
        static $rtlClasses = null;
        if ($rtlClasses === null) {
            $rtlClasses = [
                IntlChar::getPropertyValueEnum(IntlChar::PROPERTY_BIDI_CLASS, 'R'),
                IntlChar::getPropertyValueEnum(IntlChar::PROPERTY_BIDI_CLASS, 'AL'),
                IntlChar::getPropertyValueEnum(IntlChar::PROPERTY_BIDI_CLASS, 'AN'),
            ];
        }
        $hasRtl = false;
        $hasMirrored = false;
        $length = mb_strlen($text, 'UTF-8');
        for ($i = 0; $i < $length; $i++) {
            $codePoint = mb_ord(mb_substr($text, $i, 1, 'UTF-8'), 'UTF-8');
            if ($codePoint === false) {
                continue;
            }
            if (IntlChar::hasBinaryProperty($codePoint, IntlChar::PROPERTY_BIDI_MIRRORED)) {
                $hasMirrored = true;
            }
            $bidiClass = IntlChar::getIntPropertyValue($codePoint, IntlChar::PROPERTY_BIDI_CLASS);
            if (in_array($bidiClass, $rtlClasses, true)) {
                $hasRtl = true;
            }
            if ($hasRtl && $hasMirrored) {
                $stats['advisories']['had_mirrored_punctuation'] = true;
                break;
            }
        }
        return $text;
    }

    /** Final cleanup */
    private static function finalCleanup(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $rawLines = explode("\n", $text);
        $paragraphs = [];
        $buffer = [];

        foreach ($rawLines as $line) {
            $line = trim($line);

            if ($line === '') {
                if ($buffer !== []) {
                    $paragraphs[] = self::joinParagraph($buffer);
                    $buffer = [];
                }
                continue;
            }

            $normalizedLine = preg_replace('/\s+/u', ' ', $line);

            if ($buffer !== []) {
                $currentParagraph = self::joinParagraph($buffer);
                if (self::shouldForceParagraphBreak($currentParagraph, $normalizedLine)) {
                    $paragraphs[] = $currentParagraph;
                    $buffer = [];
                }
            }

            $buffer[] = $normalizedLine;
        }

        if ($buffer !== []) {
            $paragraphs[] = self::joinParagraph($buffer);
        }

        $paragraphs = array_values(array_filter($paragraphs, static function (string $paragraph): bool {
            return $paragraph !== '';
        }));

        if ($paragraphs === []) {
            return '';
        }

        $text = implode("\n\n", $paragraphs);
        $text = preg_replace('/ ([.,!?;:])/u', '$1', $text);

        return rtrim($text, "\n") . "\n";
    }

    private static function joinParagraph(array $lines): string
    {
        $joined = trim(implode(' ', $lines));
        return preg_replace('/\s+/u', ' ', $joined);
    }

    private static function shouldForceParagraphBreak(string $previousParagraph, string $nextLine): bool
    {
        if (preg_match('/^(?:\d+\.|[-*•])\s/u', $nextLine)) {
            return true;
        }

        $previousComplete = preg_match('/[\.\?!:]["\'\)\]]?$/u', $previousParagraph) === 1;
        $nextStartsSentence = preg_match('/^["\'\(\[]?[A-Z0-9]/u', $nextLine) === 1;

        return $previousComplete && $nextStartsSentence;
    }
}
