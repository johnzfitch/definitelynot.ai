<?php

declare(strict_types=1);

/**
 * Cosmic Text Linter core engine v2.3.1
 * Implements the Unicode-aware sanitization pipeline with security metrics.
 * Now includes VectorHit-based diff and reporting layer.
 */
class TextLinter
{
    public const MAX_INPUT_SIZE = 1048576; // 1 MB
    public const VERSION = '2.3.1';
    private const TRANSLITERATOR_CHUNK_SIZE = 4096; // grapheme batch size for ICU safety
    private const TRANSLITERATOR_MAX_SECONDS = 0.15; // per-request budget

    /**
     * VectorKind priority for determining primary kind when multiple apply.
     */
    private const VECTOR_KIND_PRIORITY = [
        'bidi_controls',
        'tag_characters',
        'noncharacters',
        'default_ignorables',
        'private_use',
        'non_ascii_digits',
        'orphan_combining_marks',
        'confusables',
        'mixed_scripts',
    ];

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

    // ===================================================================
    // VectorHit-based Diff and Reporting Layer
    // ===================================================================

    /**
     * Run the full sanitization pipeline and return enriched diff + vector data.
     *
     * Does not change the behavior of clean(). This is a richer, analysis-focused API.
     *
     * Usage:
     *   $result = TextLinter::analyzeWithDiff($input, 'strict');
     *   foreach ($result['hits'] as $hit) {
     *       echo "{$hit['kind']}: {$hit['note']}\n";
     *   }
     *
     * @param string $text Original text
     * @param string $mode "safe"|"aggressive"|"strict"
     * @return array LinteniumResult
     * @typedef LinteniumResult array{
     *   originalText: string,
     *   sanitizedText: string,
     *   hits: array<int,VectorHit>,
     *   summary: LinteniumSummary,
     *   diffOps: array<int,array<string,mixed>>
     * }
     */
    public static function analyzeWithDiff(string $text, string $mode = 'safe'): array
    {
        // Input validation
        if (strlen($text) > self::MAX_INPUT_SIZE) {
            throw new \InvalidArgumentException('Input exceeds maximum size of ' . self::MAX_INPUT_SIZE . ' bytes');
        }
        
        // Validate mode parameter
        $mode = in_array($mode, ['safe', 'aggressive', 'strict'], true) ? $mode : 'safe';
        
        $original = $text;

        // 1. Classify codepoints in the original text
        $classifications = self::classifyCodepoints($original);

        // 2. Run the existing sanitization pipeline
        $result = self::clean($text, $mode);
        $sanitized = $result['text'];
        $stats = $result['stats'];

        // 3. Build VectorHits from diff + classifications
        $hits = self::buildVectorHits($original, $sanitized, $classifications, $mode);

        // 4. Build summary
        $vectorCounts = [];
        foreach ($hits as $hit) {
            $k = $hit['kind'];
            $vectorCounts[$k] = ($vectorCounts[$k] ?? 0) + 1;
        }

        $notes = [];
        foreach ($vectorCounts as $kind => $count) {
            $notes[] = sprintf('%d occurrence(s) of %s', $count, $kind);
        }

        $summary = [
            'totalChanges' => count($hits),
            'vectorCounts' => $vectorCounts,
            'notes' => $notes,
        ];

        // 5. Compute diffOps for frontend reuse
        [$aClusters, $bClusters, $ops] = self::diffGraphemes($original, $sanitized);

        return [
            'originalText' => $original,
            'sanitizedText' => $sanitized,
            'hits' => $hits,
            'summary' => $summary,
            'diffOps' => $ops,
        ];
    }

     /**
      * Split a UTF-8 string into Unicode grapheme clusters.
      *
      * Grapheme indices are used throughout diffOps and VectorHits.
      *
      * NOTE: Requires the intl extension for proper grapheme cluster segmentation.
      * Without intl, falls back to codepoint splitting which has limitations:
      * - Multi-codepoint graphemes (emoji with modifiers, combined characters) will be split incorrectly
      * - Complex Unicode sequences may not be handled properly
      *
      * @param string $text
      * @return array<int,string> Grapheme clusters in order.
      */
    private static function splitGraphemes(string $text): array
    {
        if (function_exists('grapheme_str_split')) {
            $result = grapheme_str_split($text);
            if (is_array($result)) {
                return $result;
            }
        }

        // Fallback: split by codepoint using regex
        // WARNING: This does NOT properly handle grapheme clusters without intl extension
        $result = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($result) ? $result : [];
    }

    /**
     * Classify codepoints in the original text into VectorKinds.
     *
     * This is a static analysis pass that runs on the original text before any mutation.
     *
     * @param string $text Original text (pre-sanitization)
     * @return array<int,array{
     *   graphemeIndex:int,
     *   cp:int,
     *   char:string,
     *   kinds:string[]
     * }>
     */
    private static function classifyCodepoints(string $text): array
    {
        $graphemes = self::splitGraphemes($text);
        $classifications = [];

        foreach ($graphemes as $graphemeIndex => $grapheme) {
            $length = mb_strlen($grapheme, 'UTF-8');
            for ($i = 0; $i < $length; $i++) {
                $char = mb_substr($grapheme, $i, 1, 'UTF-8');
                $cp = mb_ord($char, 'UTF-8');
                if ($cp === false) {
                    continue;
                }

                $kinds = self::detectVectorKinds($cp, $char);

                if (!empty($kinds)) {
                    $classifications[] = [
                        'graphemeIndex' => $graphemeIndex,
                        'cp' => $cp,
                        'char' => $char,
                        'kinds' => $kinds,
                    ];
                }
            }
        }

        return $classifications;
    }

    /**
     * Detect which VectorKinds a codepoint belongs to.
     *
     * @param int $cp Codepoint value
     * @param string $char UTF-8 character
     * @return string[] Array of VectorKind values
     */
    private static function detectVectorKinds(int $cp, string $char): array
    {
        $kinds = [];

        // BiDi controls
        if (($cp >= 0x202A && $cp <= 0x202E) ||
            ($cp >= 0x2066 && $cp <= 0x2069) ||
            ($cp >= 0x200E && $cp <= 0x200F) ||
            $cp === 0x061C) {
            $kinds[] = 'bidi_controls';
        }

        // Default ignorables (invisibles)
        // NOTE: U+200E-U+200F (LRM/RLM) are excluded as they're already classified as bidi_controls
        // Note: U+200E-200F and U+061C are BiDi marks, classified above
        $invisibleRanges = [
            [0x200B, 0x200D], [0x2028, 0x2029], [0x2060, 0x2064],
            [0x206A, 0x206F], [0xFEFF, 0xFEFF], [0xFFF9, 0xFFFB],
            [0x00AD, 0x00AD], [0x180E, 0x180E],
            [0xFE00, 0xFE0E], [0xE0100, 0xE01EF],
            [0x061C, 0x061C],
            [0xFE0F, 0xFE0F], [0x180B, 0x180D],
        ];
        foreach ($invisibleRanges as [$start, $end]) {
            if ($cp >= $start && $cp <= $end) {
                $kinds[] = 'default_ignorables';
                break;
            }
        }

        // TAG block characters
        if ($cp >= 0xE0000 && $cp <= 0xE007F) {
            $kinds[] = 'tag_characters';
        }

        // Noncharacters
        if (($cp >= 0xFDD0 && $cp <= 0xFDEF) || ($cp & 0xFFFE) === 0xFFFE) {
            $kinds[] = 'noncharacters';
        }

        // Private Use Area
        if (($cp >= 0xE000 && $cp <= 0xF8FF) ||
            ($cp >= 0xF0000 && $cp <= 0xFFFFD) ||
            ($cp >= 0x100000 && $cp <= 0x10FFFD)) {
            $kinds[] = 'private_use';
        }

        // Non-ASCII digits
        if (class_exists('IntlChar')) {
            $digit = IntlChar::charDigitValue($cp);
            if (is_int($digit) && $digit >= 0 && $digit <= 9 && ($cp < 0x30 || $cp > 0x39)) {
                $kinds[] = 'non_ascii_digits';
            }
        } elseif (($cp >= 0x0660 && $cp <= 0x0669) || ($cp >= 0x06F0 && $cp <= 0x06F9)) {
            $kinds[] = 'non_ascii_digits';
        }

        // Combining marks
        // NOTE: This is a simplified heuristic that marks ALL combining marks as potential orphans.
        // A proper implementation would check if the combining mark follows a base character.
        // For now, this classifies all combining marks as 'orphan_combining_marks'.
        if (class_exists('IntlChar')) {
            $category = IntlChar::charType($cp);
            if ($category === IntlChar::CHAR_CATEGORY_NON_SPACING_MARK ||
                $category === IntlChar::CHAR_CATEGORY_ENCLOSING_MARK ||
                $category === IntlChar::CHAR_CATEGORY_COMBINING_SPACING_MARK) {
                $kinds[] = 'orphan_combining_marks';
            }
        }

        // Confusables & mixed scripts - simplified placeholder
        // In production, you'd use Spoofchecker or a confusables table
        if ($cp > 0x7F) {
            // For now, mark non-ASCII letters as potential confusables/mixed_scripts
            // This is a placeholder; real implementation would use proper confusable detection
            if (class_exists('IntlChar')) {
                if (IntlChar::isalpha($cp)) {
                    // Mark as potential confusable - real implementation would check against tables
                    // $kinds[] = 'confusables';
                    // $kinds[] = 'mixed_scripts';
                }
            }
        }

        return $kinds;
    }

    /**
     * Compute a grapheme-level diff between two strings.
     *
     * Uses sebastian/diff library to compute Myers diff on grapheme sequences.
     *
     * @param string $a Original text
     * @param string $b Sanitized text
     * @return array{
     *   0: array<int,string>,
     *   1: array<int,string>,
     *   2: array<int,array<string,mixed>>
     * } [$aClusters, $bClusters, $ops]
     */
    private static function diffGraphemes(string $a, string $b): array
    {
        $aClusters = self::splitGraphemes($a);
        $bClusters = self::splitGraphemes($b);

        // Use sebastian/diff if available
        if (class_exists(\SebastianBergmann\Diff\Differ::class)) {
            try {
                $differ = new \SebastianBergmann\Diff\Differ();
                $diff = $differ->diffToArray($aClusters, $bClusters);

                $ops = self::normalizeSebastianDiffOps($diff, $aClusters, $bClusters);
                return [$aClusters, $bClusters, $ops];
            } catch (\Throwable $e) {
                // Log the error and fall through to simple diff
                error_log('[CosmicTextLinter] sebastian/diff failed: ' . $e->getMessage());
                self::logSecurityEvent('sebastian_diff_failure', 'sebastian/diff failed: ' . $e->getMessage() . '; falling back to LCS diff');
            }
        }

        // Fallback: simple LCS-based diff
        $ops = self::simpleDiff($aClusters, $bClusters);
        return [$aClusters, $bClusters, $ops];
    }

    /**
     * Normalize sebastian/diff output into our standardized op format.
     *
     * @param array $diff Sebastian diff array
     * @param array $aClusters Original grapheme clusters
     * @param array $bClusters Sanitized grapheme clusters
     * @return array<int,array<string,mixed>>
     */
    private static function normalizeSebastianDiffOps(array $diff, array $aClusters, array $bClusters): array
    {
        $ops = [];
        $aIndex = 0;
        $bIndex = 0;

        foreach ($diff as $entry) {
            $token = $entry[0];
            $type = $entry[1];

            if ($type === 0) { // EQUAL
                $ops[] = [
                    'type' => 'equal',
                    'aStart' => $aIndex,
                    'aLen' => 1,
                    'bStart' => $bIndex,
                    'bLen' => 1,
                ];
                $aIndex++;
                $bIndex++;
            } elseif ($type === 1) { // DELETE
                $ops[] = [
                    'type' => 'delete',
                    'aStart' => $aIndex,
                    'aLen' => 1,
                    'bStart' => $bIndex,
                    'bLen' => 0,
                ];
                $aIndex++;
            } elseif ($type === 2) { // INSERT
                $ops[] = [
                    'type' => 'insert',
                    'aStart' => $aIndex,
                    'aLen' => 0,
                    'bStart' => $bIndex,
                    'bLen' => 1,
                ];
                $bIndex++;
            }
        }

        // Merge consecutive ops of the same type
        return self::mergeConsecutiveOps($ops);
    }

    /**
     * Merge consecutive operations of the same type.
     *
     * @param array $ops
     * @return array
     */
    private static function mergeConsecutiveOps(array $ops): array
    {
        if (empty($ops)) {
            return [];
        }

        $merged = [];
        $current = $ops[0];

        for ($i = 1; $i < count($ops); $i++) {
            $next = $ops[$i];

            if ($next['type'] === $current['type'] &&
                $next['aStart'] === $current['aStart'] + $current['aLen'] &&
                $next['bStart'] === $current['bStart'] + $current['bLen']) {
                // Merge
                $current['aLen'] += $next['aLen'];
                $current['bLen'] += $next['bLen'];
            } else {
                $merged[] = $current;
                $current = $next;
            }
        }

        $merged[] = $current;
        return $merged;
    }

    /**
     * Simple fallback diff implementation using LCS.
     *
     * @param array $a
     * @param array $b
     * @return array<int,array<string,mixed>>
     */
    private static function simpleDiff(array $a, array $b): array
    {
        $m = count($a);
        $n = count($b);

        // Build LCS table
        $lcs = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                if ($a[$i - 1] === $b[$j - 1]) {
                    $lcs[$i][$j] = $lcs[$i - 1][$j - 1] + 1;
                } else {
                    $lcs[$i][$j] = max($lcs[$i - 1][$j], $lcs[$i][$j - 1]);
                }
            }
        }

        // Backtrack to build ops
        $ops = [];
        $i = $m;
        $j = $n;

        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $a[$i - 1] === $b[$j - 1]) {
                array_unshift($ops, [
                    'type' => 'equal',
                    'aStart' => $i - 1,
                    'aLen' => 1,
                    'bStart' => $j - 1,
                    'bLen' => 1,
                ]);
                $i--;
                $j--;
            } elseif ($j > 0 && ($i === 0 || $lcs[$i][$j - 1] >= $lcs[$i - 1][$j])) {
                array_unshift($ops, [
                    'type' => 'insert',
                    'aStart' => $i,
                    'aLen' => 0,
                    'bStart' => $j - 1,
                    'bLen' => 1,
                ]);
                $j--;
            } else {
                array_unshift($ops, [
                    'type' => 'delete',
                    'aStart' => $i - 1,
                    'aLen' => 1,
                    'bStart' => $j,
                    'bLen' => 0,
                ]);
                $i--;
            }
        }

        return self::mergeConsecutiveOps($ops);
    }

    /**
     * Build VectorHits from the original text, sanitized text, and codepoint classifications.
     *
     * @param string $original   Original text
     * @param string $sanitized  Sanitized text
     * @param array<int,array{graphemeIndex:int,cp:int,char:string,kinds:string[]}> $classifications
     * @param string $mode       "safe"|"aggressive"|"strict"
     * @return array<int,array>  Array of VectorHit
     */
    private static function buildVectorHits(
        string $original,
        string $sanitized,
        array $classifications,
        string $mode
    ): array {
        [$aClusters, $bClusters, $ops] = self::diffGraphemes($original, $sanitized);

        $hits = [];

        foreach ($ops as $op) {
            if ($op['type'] === 'equal') {
                continue;
            }

            $origStart = $op['aStart'];
            $origEnd = $op['aStart'] + $op['aLen'];
            $sanStart = $op['bStart'];
            $sanEnd = $op['bStart'] + $op['bLen'];

            $originalSlice = implode('', array_slice($aClusters, $origStart, $op['aLen']));
            $sanitizedSlice = implode('', array_slice($bClusters, $sanStart, $op['bLen']));

            // Collect kinds and codepoints from classifications
            $kindCounts = [];
            $codePoints = [];

            foreach ($classifications as $c) {
                if ($c['graphemeIndex'] >= $origStart && $c['graphemeIndex'] < $origEnd) {
                    foreach ($c['kinds'] as $kind) {
                        $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + 1;
                    }
                    $codePoints[] = sprintf('U+%05X', $c['cp']);
                }
            }

            // Remove duplicates
            $codePoints = array_values(array_unique($codePoints));

            // If no kinds detected, skip (no security issue)
            if (empty($kindCounts)) {
                continue;
            }

            // Create a VectorHit for each kind present
            foreach ($kindCounts as $kind => $count) {
                $hits[] = [
                    'id' => uniqid($kind . '_', true),
                    'kind' => $kind,
                    'severity' => self::severityForKind($kind, $mode),
                    'originalRange' => ['startGrapheme' => $origStart, 'endGrapheme' => $origEnd],
                    'sanitizedRange' => ['startGrapheme' => $sanStart, 'endGrapheme' => $sanEnd],
                    'originalSlice' => $originalSlice,
                    'sanitizedSlice' => $sanitizedSlice,
                    'codePoints' => $codePoints,
                    'note' => self::noteForKind($kind),
                ];
            }
        }

        return $hits;
    }

    /**
     * Determine severity level for a VectorKind.
     *
     * @param string $kind VectorKind
     * @param string $mode Sanitization mode
     * @return string "info"|"warn"|"block"
     */
    private static function severityForKind(string $kind, string $mode): string
    {
        $blockKinds = ['bidi_controls', 'tag_characters', 'noncharacters'];
        if (in_array($kind, $blockKinds, true)) {
            return 'block';
        }

        $warnKinds = ['default_ignorables', 'private_use', 'non_ascii_digits'];
        if (in_array($kind, $warnKinds, true)) {
            return 'warn';
        }

        // info kinds: orphan_combining_marks, confusables, mixed_scripts
        if ($mode === 'safe') {
            return 'info';
        }

        return 'warn';
    }

    /**
     * Generate a human-readable note for a VectorKind.
     *
     * @param string $kind VectorKind
     * @return string Descriptive note
     */
    private static function noteForKind(string $kind): string
    {
        $notes = [
            'bidi_controls' => 'BiDi control characters detected; potential text direction spoofing.',
            'mixed_scripts' => 'Mixed script usage detected; potential confusable attack.',
            'default_ignorables' => 'Invisible or default-ignorable characters detected.',
            'tag_characters' => 'TAG block characters detected; potential hidden data.',
            'orphan_combining_marks' => 'Orphan combining marks detected; potential rendering issues.',
            'confusables' => 'Confusable characters detected; potential visual spoofing.',
            'noncharacters' => 'Unicode noncharacters detected; invalid for interchange.',
            'private_use' => 'Private Use Area characters detected; undefined semantics.',
            'non_ascii_digits' => 'Non-ASCII digits detected; normalized to ASCII.',
        ];

        return $notes[$kind] ?? 'Security-relevant character detected.';
    }

}
