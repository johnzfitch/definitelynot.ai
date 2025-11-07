<?php

declare(strict_types=1);

/**
 * Cosmic Text Linter core engine v2.3.1
 * Implements the Unicode-aware sanitization pipeline with security metrics.
 *
 * Security Update v2.3.0:
 * - Fixed variation selector logic (proper handling of all 16 selectors for AI watermark removal)
 * - Unicode spaces normalized (not removed) to preserve text structure
 * - Improved code organization and documentation
 *
 * Update v2.3.1:
 * - Preserve markdown formatting in safe mode (*, _, `, etc.)
 * - Only strip markdown in aggressive/strict modes
 * - Focus on AI watermark removal (variation selectors, zero-width chars)
 */
class TextLinter
{
    public const MAX_INPUT_SIZE = 1048576; // 1 MB
    public const VERSION = '2.3.1';
    private const TRANSLITERATOR_CHUNK_SIZE = 4096; // grapheme batch size for ICU safety
    private const TRANSLITERATOR_MAX_SECONDS = 0.15; // per-request budget

    /**
     * Execute the sanitization pipeline.
     *
     * @param string $text Raw input text (UTF-8)
     * @param string $mode safe|aggressive|strict
     * @return array{text:string,stats:array<string,mixed>} Result payload
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
        $text = self::stripFormatting($text, $mode);
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

    /** Step 1: Decode HTML entities */
    private static function decodeEntities(string $text, array &$stats): string
    {
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        if ($decoded !== $text) {
            $stats['advisories']['had_html_entities'] = true;
        }
        return $decoded;
    }

    /** Step 2: Strip ASCII control characters except TAB and LF */
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

    /** Step 3: Strip BiDi controls */
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
                $length = mb_strlen($text, 'UTF-8');
                $processed = 0;
                $accumulator = '';
                $success = true;
                $start = microtime(true);

                while ($processed < $length) {
                    if ((microtime(true) - $start) > self::TRANSLITERATOR_MAX_SECONDS) {
                        $success = false;
                        self::logSecurityEvent('transliterator_timeout', 'NFKC transliteration exceeded time budget; falling back.');
                        break;
                    }

                    $chunk = mb_substr($text, $processed, self::TRANSLITERATOR_CHUNK_SIZE, 'UTF-8');
                    if ($chunk === '') {
                        break;
                    }

                    $converted = $trans->transliterate($chunk);
                    if ($converted === false) {
                        $success = false;
                        self::logSecurityEvent('transliterator_failure', 'NFKC transliteration failed; falling back.');
                        break;
                    }

                    $accumulator .= $converted;
                    $processed += mb_strlen($chunk, 'UTF-8');
                }

                if ($success) {
                    return $accumulator;
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
        // Core invisible characters (zero-width, format controls, soft hyphens)
        $invisibles = [
            '\x{200B}', // Zero-width space
            '\x{200C}', // Zero-width non-joiner (preserved in safe mode for text shaping)
            '\x{200D}', // Zero-width joiner (preserved in safe mode for text shaping)
            '\x{2028}', // Line separator
            '\x{2029}', // Paragraph separator
            '\x{2060}', // Word joiner
            '\x{2061}', // Function application
            '\x{2062}', // Invisible times
            '\x{2063}', // Invisible separator
            '\x{2064}', // Invisible plus
            '\x{206A}', // Inhibit symmetric swapping
            '\x{206B}', // Activate symmetric swapping
            '\x{206C}', // Inhibit Arabic form shaping
            '\x{206D}', // Activate Arabic form shaping
            '\x{206E}', // National digit shapes
            '\x{206F}', // Nominal digit shapes
            '\x{FEFF}', // Zero-width no-break space (BOM)
            '\x{FFF9}', // Interlinear annotation anchor
            '\x{FFFA}', // Interlinear annotation separator
            '\x{FFFB}', // Interlinear annotation terminator
            '\x{00AD}', // Soft hyphen
            '\x{180E}', // Mongolian vowel separator
        ];

        // In safe mode, preserve text-shaping characters for complex scripts
        if ($mode === 'safe') {
            $safeSet = array_flip(['\x{200C}', '\x{200D}']);
            $invisibles = array_values(array_filter($invisibles, function ($item) use ($safeSet) {
                return !isset($safeSet[$item]);
            }));
        }

        // Directional marks (removed in aggressive/strict modes only)
        if ($mode !== 'safe') {
            $invisibles[] = '\x{200E}'; // Left-to-right mark
            $invisibles[] = '\x{200F}'; // Right-to-left mark
            $invisibles[] = '\x{061C}'; // Arabic letter mark
        }

        // Variation selectors (U+FE00 to U+FE0F) - AI watermark removal
        // These are truly invisible and used for AI watermarking
        // Fixed: Properly handle all 16 variation selectors based on mode
        $vsEnd = ($mode === 'aggressive' || $mode === 'strict') ? 0xFE0F : 0xFE0E;
        for ($i = 0xFE00; $i <= $vsEnd; $i++) {
            $invisibles[] = '\x{' . strtoupper(dechex($i)) . '}';
        }

        // Mongolian free variation selectors and variation selector supplements
        // These are used for AI watermarking and should be removed in aggressive/strict
        if ($mode === 'aggressive' || $mode === 'strict') {
            $invisibles[] = '\x{180B}'; // Mongolian free variation selector one
            $invisibles[] = '\x{180C}'; // Mongolian free variation selector two
            $invisibles[] = '\x{180D}'; // Mongolian free variation selector three

            // Variation Selectors Supplement (U+E0100 to U+E01EF) - AI watermark removal
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
            $shouldNormalize = false;
            $cp = mb_ord($ch, 'UTF-8');
            if ($cp === false) {
                $buffer .= $ch;
                continue;
            }

            if (class_exists('IntlChar')) {
                $digit = IntlChar::charDigitValue($cp);
                if (!is_int($digit) || $digit < 0 || $digit > 9) {
                    $digit = null;
                } else {
                    // Only normalize if the original glyph was not ASCII 0-9.
                    $shouldNormalize = ($cp < 0x30 || $cp > 0x39);
                }
            }

            if ($digit === null) {
                if ($cp >= 0x0660 && $cp <= 0x0669) {
                    $digit = $cp - 0x0660;
                    $shouldNormalize = true;
                } elseif ($cp >= 0x06F0 && $cp <= 0x06F9) {
                    $digit = $cp - 0x06F0;
                    $shouldNormalize = true;
                }
            }

            if ($digit !== null && $digit >= 0 && $digit <= 9 && $shouldNormalize) {
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

    /** Step 10: Strip markdown/HTML formatting (only in aggressive/strict modes) */
    private static function stripFormatting(string $text, string $mode): string
    {
        // Always normalize Unicode bullets to hyphens
        $bullets = ['•', '◦', '▪', '‣', '⁃', '⁌', '⁍', '∙', '○', '●', '◘', '◙'];
        $text = str_replace($bullets, '-', $text);

        // Always strip HTML tags for security
        $text = strip_tags($text);

        // Only strip markdown in aggressive/strict modes (preserve for safe mode)
        if ($mode === 'aggressive' || $mode === 'strict') {
            $text = preg_replace('/\*([^*\n]{1,50})\*/u', '$1', $text);
            $text = preg_replace('/_([^_\n]{1,50})_/u', '$1', $text);
        }

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
            self::logSecurityEvent('spoofchecker_missing', 'Spoofchecker extension unavailable; spoof audit skipped.');
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

    private static function logSecurityEvent(string $key, string $message): void
    {
        static $logged = [];
        if (isset($logged[$key])) {
            return;
        }
        $logged[$key] = true;
        error_log('[CosmicTextLinter] ' . $message);
    }

}
