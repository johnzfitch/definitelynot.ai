# VectorHit Diff Layer - Documentation

## Overview

The VectorHit diff layer adds detailed security analysis and diff tracking on top of the existing TextLinter sanitization pipeline. It works at Unicode grapheme cluster granularity and provides rich metadata about each security-relevant change.

## Data Structures

### VectorKind

Allowed `kind` values representing different security concerns:

- `bidi_controls` - BiDi control characters (U+202A-202E, U+2066-2069)
- `mixed_scripts` - Mixed script usage (potential confusable attack)
- `default_ignorables` - Invisible or default-ignorable characters
- `tag_characters` - TAG block characters (U+E0000-E007F)
- `orphan_combining_marks` - Combining marks without base characters
- `confusables` - Confusable characters (visual spoofing)
- `noncharacters` - Unicode noncharacters (U+FDD0-FDEF, etc.)
- `private_use` - Private Use Area characters
- `non_ascii_digits` - Non-ASCII digit characters

### VectorHit

```php
[
  'id' => string,              // Unique identifier
  'kind' => string,            // VectorKind value
  'severity' => string,        // "info"|"warn"|"block"
  'originalRange' => [
    'startGrapheme' => int,    // Start index in original (inclusive)
    'endGrapheme' => int       // End index in original (exclusive)
  ],
  'sanitizedRange' => [
    'startGrapheme' => int,    // Start index in sanitized (inclusive)
    'endGrapheme' => int       // End index in sanitized (exclusive)
  ],
  'originalSlice' => string,   // The original text slice
  'sanitizedSlice' => string,  // The sanitized text slice
  'codePoints' => string[],    // Array of codepoints like ["U+202E", "U+200B"]
  'note' => string             // Human-readable description
]
```

### LinteniumSummary

```php
[
  'totalChanges' => int,                    // Total number of VectorHits
  'vectorCounts' => [                       // Count per VectorKind
    'bidi_controls' => int,
    'default_ignorables' => int,
    // ... etc
  ],
  'notes' => string[]                       // Human-readable summary notes
]
```

### LinteniumResult

```php
[
  'originalText' => string,                 // Original input text
  'sanitizedText' => string,                // Sanitized output text
  'hits' => VectorHit[],                    // Array of VectorHits
  'summary' => LinteniumSummary,            // Aggregate summary
  'diffOps' => [                            // Grapheme-level diff operations
    [
<<<<<<< copilot/sub-pr-11
      'type' => 'equal'|'delete'|'insert',
=======
      'type' => 'equal'|'delete'|'insert', // Operation type
>>>>>>> claude/add-vectorhit-diff-layer-017nn5LVNJKGDda5w8orGamz
      'aStart' => int,                      // Grapheme index in original
      'aLen' => int,                        // Grapheme count in original
      'bStart' => int,                      // Grapheme index in sanitized
      'bLen' => int                         // Grapheme count in sanitized
    ],
    // Note: 'replace' operations are represented as consecutive 'delete' + 'insert' ops
    // ...
  ]
]
```

## API Usage

### analyzeWithDiff()

The main entry point for enriched analysis:

```php
<?php
require_once 'api/TextLinter.php';

$input = "Hello \u{202E}world\u{202C} test";
$result = TextLinter::analyzeWithDiff($input, 'aggressive');

// Access results
echo "Original: {$result['originalText']}\n";
echo "Sanitized: {$result['sanitizedText']}\n";
echo "Total changes: {$result['summary']['totalChanges']}\n";

// Iterate over hits
foreach ($result['hits'] as $hit) {
    echo "[{$hit['severity']}] {$hit['kind']}: {$hit['note']}\n";
    echo "  Original: {$hit['originalSlice']}\n";
    echo "  Sanitized: {$hit['sanitizedSlice']}\n";
    echo "  Codepoints: " . implode(', ', $hit['codePoints']) . "\n";
}

// Access diff operations
foreach ($result['diffOps'] as $op) {
    echo "Op: {$op['type']} at grapheme {$op['aStart']}\n";
}
```

### Modes

- `safe` - Preserves ZWJ/ZWNJ for complex scripts
- `aggressive` - More aggressive sanitization
- `strict` - NFKC casefold + maximum sanitization

### Severity Levels

- `block` - High-risk security issue (BiDi controls, TAG chars, noncharacters)
- `warn` - Medium-risk (invisibles, private use, non-ASCII digits)
- `info` - Low-risk informational (in safe mode: combining marks, confusables)

## Grapheme Indexing

All indices in VectorHits and diffOps are measured in **grapheme clusters**, not bytes or codepoints. This ensures proper handling of:

- Multi-codepoint emoji (👨‍👩‍👧‍👦)
- Combining character sequences (é = e + ́)
- Complex scripts with joiners

Ranges use half-open intervals: `[start, end)` where `start` is inclusive and `end` is exclusive.

## Implementation Details

### Grapheme Segmentation

Uses `grapheme_str_split()` from the `intl` extension when available. Falls back to simple codepoint splitting otherwise.

### Diff Algorithm

Uses `sebastian/diff` library (Myers diff) when available via Composer. Falls back to a simple LCS-based diff implementation if the library is not available.

### Classification

The `classifyCodepoints()` method performs static analysis on the original text before sanitization, detecting all VectorKinds for each codepoint. The diff layer then combines this classification data with the diff results to generate VectorHits.

## Backward Compatibility

The existing `TextLinter::clean()` method remains **unchanged** and continues to work exactly as before:

```php
$result = TextLinter::clean($text, 'aggressive');
// Returns: ['text' => string, 'stats' => array]
```

All existing stats keys and advisory flags are preserved.

## Dependencies

### Required
- PHP >= 7.4

### Recommended
- `ext-intl` - For proper grapheme segmentation and Unicode property detection
- `ext-mbstring` - For multibyte string handling
- `sebastian/diff` - For efficient Myers diff algorithm

### Installation

```bash
composer install
```

This will install the `sebastian/diff` library for optimal performance.

## Testing

Run the test suite:

```bash
php test_vectorhit.php
```

This will test:
- BiDi control detection
- Invisible character detection
- Non-ASCII digit normalization
- Private Use Area detection
- Clean text handling
- Backward compatibility with clean()
