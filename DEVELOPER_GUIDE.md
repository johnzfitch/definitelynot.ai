# Developer Guide

> Complete guide for developers working on the Cosmic Text Linter project

## Table of Contents

- [Getting Started](#getting-started)
- [Development Environment](#development-environment)
- [Code Structure](#code-structure)
- [Core Components](#core-components)
- [Development Workflow](#development-workflow)
- [Testing](#testing)
- [Debugging](#debugging)
- [Performance Optimization](#performance-optimization)
- [Extending the System](#extending-the-system)
- [Code Style Guide](#code-style-guide)
- [Common Tasks](#common-tasks)

## Getting Started

### Prerequisites

- **PHP 7.4+** with extensions:
  - `mbstring` - Multibyte string functions
  - `intl` - International functions (ICU)
  - `json` - JSON encoding/decoding
- **Web server**: Apache 2.4+ or Nginx 1.18+
- **Git** for version control
- **Code editor** with PHP support (VSCode, PhpStorm, etc.)

### Initial Setup

```bash
# Clone the repository
git clone https://github.com/johnzfitch/definitelynot.ai.git
cd definitelynot.ai

# Verify PHP and extensions
php -v
php -m | grep -E 'mbstring|intl|json'

# Check ICU version
php -r "echo IntlChar::getUnicodeVersion();"

# Start development server
cd cosmic-text-linter
php -S localhost:8000

# In another terminal, run tests
cd tests
chmod +x smoke-test.sh
API_URL="http://localhost:8000/api/clean.php" ./smoke-test.sh
```

### IDE Configuration

#### VSCode

Create `.vscode/settings.json`:

```json
{
  "php.validate.executablePath": "/usr/bin/php",
  "php.suggest.basic": true,
  "files.associations": {
    "*.php": "php"
  },
  "editor.formatOnSave": true,
  "editor.tabSize": 4,
  "editor.insertSpaces": true,
  "[javascript]": {
    "editor.tabSize": 2
  },
  "[css]": {
    "editor.tabSize": 2
  }
}
```

#### PhpStorm

1. File → Settings → PHP
2. Set PHP language level to 7.4
3. Configure interpreter path
4. Enable `mbstring` and `intl` extensions

## Development Environment

### Directory Structure

```
cosmic-text-linter/
├── api/                      # Backend API
│   ├── TextLinter.php       # Core sanitization engine
│   └── clean.php            # REST endpoint
├── assets/                   # Frontend assets
│   ├── css/
│   │   └── styles.css       # Cosmic theme styles
│   └── js/
│       └── script.js        # Frontend controller
├── tests/                    # Test suite
│   ├── test-samples.txt     # Test cases
│   └── smoke-test.sh        # Automated tests
├── index.html               # Web UI entry point
├── .htaccess                # Apache configuration
└── README.md                # User documentation
```

### File Responsibilities

| File | Purpose | Language | Lines |
|------|---------|----------|-------|
| `api/TextLinter.php` | Core sanitization logic | PHP | 541 |
| `api/clean.php` | RESTful API endpoint | PHP | 103 |
| `assets/js/script.js` | Frontend controller | JavaScript | 529 |
| `assets/css/styles.css` | UI styling | CSS | 1,110 |
| `index.html` | User interface | HTML | 197 |
| `.htaccess` | Server configuration | Apache | 47 |

## Code Structure

### Backend Architecture

#### TextLinter.php

```
TextLinter (static class)
│
├── clean($text, $mode)              # Main entry point
│   ├── Step 1-16 processing         # Sanitization pipeline
│   ├── Advisory tracking            # Security detection
│   └── Statistics collection        # Metrics gathering
│
├── Helper methods (private static)
│   ├── removeInvisibleCharacters()
│   ├── normalizeHomoglyphs()
│   ├── removeBidiControls()
│   ├── detectMixedScripts()
│   └── auditSpoofing()
│
└── Constants
    ├── VERSION = "2.2.1"
    └── MAX_COMBINING_MARKS
```

**Key Methods:**

```php
/**
 * Main sanitization method
 *
 * @param string $text Input text to sanitize
 * @param string $mode Operation mode: safe|aggressive|strict
 * @return array ['text' => string, 'stats' => array]
 */
public static function clean($text, $mode = 'safe')
```

#### clean.php

```
Request Flow:
1. Validate Content-Type header
2. Read and decode JSON body
3. Validate payload structure
4. Call TextLinter::clean()
5. Build JSON response
6. Set CORS headers
7. Return HTTP 200 or error
```

**Structure:**

```php
// Input validation
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') === false) {
    http_response_code(415);
    exit(json_encode(['error' => 'Content-Type must be application/json']));
}

// Process request
$input = json_decode(file_get_contents('php://input'), true);
$result = TextLinter::clean($input['text'], $input['mode'] ?? 'safe');

// Return response
header('Content-Type: application/json');
echo json_encode($result);
```

### Frontend Architecture

#### script.js

```
Module Organization:
│
├── Configuration
│   ├── API_URL
│   ├── MAX_TEXT_SIZE
│   └── DEFAULT_MODE
│
├── Event Handlers
│   ├── handleSanitize()         # Main sanitization trigger
│   ├── handleModeChange()       # Mode selector
│   ├── handleThemeChange()      # Color picker
│   └── handleKeyboardShortcuts() # Ctrl+Enter
│
├── API Communication
│   ├── callAPI(text, mode)      # Fetch wrapper
│   └── handleAPIError(error)    # Error handling
│
├── UI Updates
│   ├── updateOutput(result)     # Display sanitized text
│   ├── updateStatistics(stats)  # Show metrics
│   ├── updateAdvisories(advisories) # Security warnings
│   └── updateCharacterCount()   # Real-time counter
│
└── Utilities
    ├── showToast(message, type)
    ├── showModal(content)
    └── validateInput(text)
```

**Key Functions:**

```javascript
/**
 * Sanitize text via API call
 * @param {string} text - Input text
 * @param {string} mode - Operation mode
 * @returns {Promise<Object>} API response
 */
async function callAPI(text, mode) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 10000);

  try {
    const response = await fetch(API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ text, mode }),
      signal: controller.signal
    });

    clearTimeout(timeout);

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    return await response.json();
  } catch (error) {
    clearTimeout(timeout);
    throw error;
  }
}
```

#### styles.css

```
CSS Organization:
│
├── CSS Variables (root)
│   ├── --accent-color
│   ├── --bg-color
│   └── Typography tokens
│
├── Base Styles
│   ├── Reset
│   ├── Typography
│   └── Layout
│
├── Components
│   ├── .container
│   ├── .input-panel
│   ├── .output-panel
│   ├── .statistics
│   └── .modal
│
├── Animations
│   ├── @keyframes starfield
│   ├── @keyframes glow
│   └── Transitions
│
└── Responsive
    ├── @media (max-width: 768px)
    └── @media (prefers-reduced-motion)
```

## Core Components

### 1. Sanitization Pipeline (TextLinter.php)

The 16-step pipeline is the heart of the system:

```php
// Step 1: HTML Entity Decode
$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

// Step 2: Remove ASCII Controls
$text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/u', '', $text);

// Step 3: Remove BiDi Controls
$bidiPattern = '/[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';
if (preg_match($bidiPattern, $text)) {
    $advisories['had_bidi_controls'] = true;
    $text = preg_replace($bidiPattern, '', $text);
}

// Step 4: Unicode Normalization
$normalizer = ($mode === 'strict') ? \Normalizer::NFKC : \Normalizer::NFC;
$text = \Normalizer::normalize($text, $normalizer);
if ($mode === 'strict') {
    $text = mb_strtolower($text, 'UTF-8');
}

// ... Steps 5-16 continue
```

### 2. Advisory System

Tracks security threats detected during sanitization:

```php
$advisories = [
    'had_bidi_controls' => false,        // Trojan Source
    'had_mixed_scripts' => false,        // Script confusion
    'had_default_ignorables' => false,   // Invisibles
    'had_tag_chars' => false,            // TAG injection
    'had_orphan_combining' => false,     // Zalgo text
    'confusable_suspected' => false,     // Homoglyphs
    'had_html_entities' => false,        // Encoding issues
    'had_ascii_controls' => false,       // Control chars
    'had_noncharacters' => false,        // Noncharacters
    'had_private_use' => false,          // PUA
    'had_mirrored_punctuation' => false, // RTL issues
    'had_non_ascii_digits' => false      // Digit spoofing
];
```

### 3. Statistics Collection

Tracks metrics during processing:

```php
$stats = [
    'original_length' => mb_strlen($originalText),
    'final_length' => mb_strlen($cleanedText),
    'characters_removed' => $charsRemoved,
    'mode' => $mode,
    'invisibles_removed' => $invisibleCount,
    'homoglyphs_normalized' => $homoglyphCount,
    'digits_normalized' => $digitCount,
    'advisories' => $advisories
];
```

### 4. Error Handling

Graceful degradation for missing extensions:

```php
if (!extension_loaded('intl')) {
    http_response_code(503);
    exit(json_encode([
        'error' => 'Required PHP extension not available: intl'
    ]));
}

if (!class_exists('Spoofchecker')) {
    // Spoofchecker optional - degrade gracefully
    $advisories['confusable_suspected'] = null;
}
```

## Development Workflow

### 1. Feature Development

```bash
# Create feature branch
git checkout -b feature/your-feature-name

# Make changes
# ... edit files ...

# Test locally
php -S localhost:8000
# Visit http://localhost:8000

# Run tests
cd tests
./smoke-test.sh

# Commit changes
git add .
git commit -m "Add feature: description"

# Push to remote
git push origin feature/your-feature-name

# Create pull request on GitHub
```

### 2. Bug Fixes

```bash
# Create bugfix branch
git checkout -b bugfix/issue-description

# Reproduce bug
# Add test case to test-samples.txt

# Fix bug
# ... edit code ...

# Verify fix
./smoke-test.sh

# Commit and push
git add .
git commit -m "Fix: description of bug fix"
git push origin bugfix/issue-description
```

### 3. Code Review Checklist

- [ ] Code follows style guide
- [ ] All tests pass
- [ ] No security vulnerabilities introduced
- [ ] Performance impact assessed
- [ ] Documentation updated
- [ ] Backwards compatible (or breaking changes documented)
- [ ] Error handling implemented
- [ ] Edge cases covered

## Testing

### Automated Tests (smoke-test.sh)

The smoke test suite validates core functionality:

```bash
#!/bin/bash
API_URL="${API_URL:-http://localhost:8000/api/clean.php}"

# Test 1: Basic sanitization
test_basic() {
  response=$(curl -s -X POST "$API_URL" \
    -H 'Content-Type: application/json' \
    -d '{"text":"Hello\u200Bworld","mode":"safe"}')

  echo "$response" | jq -e '.text == "Helloworld\n"' > /dev/null
  if [ $? -eq 0 ]; then
    echo "✓ Test 1: Basic sanitization passed"
  else
    echo "✗ Test 1: Basic sanitization failed"
    exit 1
  fi
}

# Run all tests
test_basic
# ... more tests
```

### Manual Testing

Use `test-samples.txt` for comprehensive manual testing:

```
# Test Case 1: Zero-Width Space Removal
Input: Hello​world (contains U+200B)
Expected (safe): Helloworld
Expected (aggressive): Helloworld
Expected (strict): helloworld

# Test Case 2: Homoglyph Detection
Input: Hеllο (Cyrillic е, Greek ο)
Expected (safe): Hеllο (preserved)
Expected (aggressive): Hello (normalized)
Expected (strict): hello (normalized + lowercased)
```

### Writing New Tests

Add to `test-samples.txt`:

```
# Test Case X: [Description]
# Attack: [Attack type]
Input: [Actual input with Unicode escapes if needed]
Expected (safe): [Expected output in safe mode]
Expected (aggressive): [Expected output in aggressive mode]
Expected (strict): [Expected output in strict mode]
Notes: [Any special considerations]
```

Add to `smoke-test.sh`:

```bash
test_your_feature() {
  local input='{"text":"test input","mode":"safe"}'
  local response=$(curl -s -X POST "$API_URL" \
    -H 'Content-Type: application/json' \
    -d "$input")

  local result=$(echo "$response" | jq -r '.text')
  local expected="expected output\n"

  if [ "$result" == "$expected" ]; then
    echo "✓ Test: Your feature passed"
  else
    echo "✗ Test: Your feature failed"
    echo "  Expected: $expected"
    echo "  Got: $result"
    exit 1
  fi
}
```

## Debugging

### PHP Debugging

Enable error logging in `.htaccess`:

```apache
php_flag display_errors On
php_flag log_errors On
php_value error_log /path/to/error.log
```

Or in `clean.php`:

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
```

### Debug Output

Add debug logging to `TextLinter.php`:

```php
private static function debug($message, $data = null) {
    if (defined('DEBUG') && DEBUG) {
        error_log("DEBUG: $message");
        if ($data !== null) {
            error_log(print_r($data, true));
        }
    }
}

// Usage
self::debug('Processing step 5', ['text_length' => mb_strlen($text)]);
```

### JavaScript Debugging

Enable verbose console logging:

```javascript
const DEBUG = true;

function debug(message, data) {
  if (DEBUG) {
    console.log(`[DEBUG] ${message}`, data);
  }
}

// Usage
debug('API Response', result);
```

### Common Issues

#### Issue: "intl extension not loaded"

**Solution:**

```bash
# Check if installed
php -m | grep intl

# Ubuntu/Debian
sudo apt-get install php-intl
sudo systemctl restart apache2

# macOS
# Edit php.ini and uncomment:
# extension=intl

# Verify
php -r "echo IntlChar::getUnicodeVersion();"
```

#### Issue: "Class 'Normalizer' not found"

**Solution:** Install/enable `intl` extension (see above).

#### Issue: "CORS error in browser"

**Solution:**

Check `.htaccess` headers:

```apache
Header set Access-Control-Allow-Origin "*"
Header set Access-Control-Allow-Methods "POST, OPTIONS"
Header set Access-Control-Allow-Headers "Content-Type"
```

Or in `clean.php`:

```php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
```

## Performance Optimization

### Backend Optimization

#### 1. OpCache Configuration

Enable PHP OpCache in `php.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
```

#### 2. String Operations

Optimize regex patterns:

```php
// Slow: Multiple passes
$text = preg_replace('/pattern1/', '', $text);
$text = preg_replace('/pattern2/', '', $text);
$text = preg_replace('/pattern3/', '', $text);

// Fast: Single pass with alternation
$text = preg_replace('/pattern1|pattern2|pattern3/', '', $text);
```

#### 3. Unicode Operations

Cache IntlChar calls:

```php
// Slow: Repeated calls
for ($i = 0; $i < mb_strlen($text); $i++) {
    $char = mb_substr($text, $i, 1);
    if (\IntlChar::isWhitespace(\IntlChar::ord($char))) {
        // ...
    }
}

// Fast: Single pass with preg_match_all
preg_match_all('/\s/u', $text, $matches);
```

### Frontend Optimization

#### 1. Debounce Input Events

```javascript
let debounceTimer;
function handleInput(event) {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    updateCharacterCount();
  }, 300);
}
```

#### 2. Lazy Load Assets

```html
<!-- Defer non-critical CSS -->
<link rel="preload" href="assets/css/styles.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
```

#### 3. Minimize Reflows

Batch DOM updates:

```javascript
// Slow: Multiple reflows
output.textContent = result.text;
stats.textContent = result.stats;
advisories.innerHTML = buildAdvisories(result);

// Fast: Single reflow
const fragment = document.createDocumentFragment();
// ... build fragment ...
container.appendChild(fragment);
```

## Extending the System

### Adding a New Sanitization Step

1. **Define the step** in `TextLinter.php`:

```php
private static function removeCustomCharacters($text, &$advisories) {
    $pattern = '/[\x{FFFF}]/u'; // Your pattern
    if (preg_match($pattern, $text)) {
        $advisories['had_custom_chars'] = true;
        $text = preg_replace($pattern, '', $text);
    }
    return $text;
}
```

2. **Add to pipeline** in `clean()`:

```php
// After step 16, before final cleanup
$text = self::removeCustomCharacters($text, $advisories);
```

3. **Update advisory tracking**:

```php
$advisories['had_custom_chars'] = false;
```

4. **Add tests** in `test-samples.txt`:

```
# Test Case: Custom Character Removal
Input: Hello\uFFFFworld
Expected (safe): Helloworld
Expected (aggressive): Helloworld
Expected (strict): helloworld
```

### Adding a New Operation Mode

1. **Add mode handling** in `TextLinter.php`:

```php
switch ($mode) {
    case 'safe':
    case 'aggressive':
    case 'strict':
        // Existing modes
        break;
    case 'paranoid':
        $normalizer = \Normalizer::NFKC;
        $removeHomoglyphs = true;
        $removePUA = true;
        $removeAllNonASCII = true; // New behavior
        break;
}
```

2. **Update documentation** in README and API_REFERENCE.

3. **Add frontend UI**:

```html
<option value="paranoid">Paranoid (ASCII only)</option>
```

### Adding Frontend Features

1. **Add UI elements** in `index.html`:

```html
<button id="copy-btn" class="btn">Copy to Clipboard</button>
```

2. **Add event handler** in `script.js`:

```javascript
document.getElementById('copy-btn').addEventListener('click', () => {
  const output = document.getElementById('output').value;
  navigator.clipboard.writeText(output);
  showToast('Copied to clipboard!', 'success');
});
```

3. **Add styling** in `styles.css`:

```css
.btn {
  background: var(--accent-color);
  padding: 10px 20px;
  border-radius: 5px;
  cursor: pointer;
}
```

## Code Style Guide

### PHP Style

```php
// Use strict types
declare(strict_types=1);

// Class names: PascalCase
class TextLinter {}

// Method names: camelCase
public static function cleanText() {}

// Private methods: camelCase with descriptive names
private static function removeInvisibleCharacters() {}

// Constants: UPPER_SNAKE_CASE
const MAX_INPUT_SIZE = 1048576;

// Variables: camelCase
$originalText = $input['text'];

// Arrays: explicit syntax
$advisories = [
    'had_bidi_controls' => false,
];

// Spacing: 4 spaces indentation
if ($condition) {
    // 4 spaces
    doSomething();
}

// Comments: PHPDoc for public methods
/**
 * Sanitize text with Unicode security checks
 *
 * @param string $text Input text
 * @param string $mode Operation mode
 * @return array Result with text and stats
 */
public static function clean(string $text, string $mode = 'safe'): array
```

### JavaScript Style

```javascript
// Use const/let, not var
const API_URL = '/api/clean.php';
let result = null;

// Function names: camelCase
function sanitizeText() {}

// Arrow functions for callbacks
array.map(item => item.value);

// Template literals for strings
const message = `Hello ${name}`;

// Async/await for promises
async function fetchData() {
  const response = await fetch(url);
  return await response.json();
}

// Spacing: 2 spaces indentation
if (condition) {
  // 2 spaces
  doSomething();
}

// Comments: JSDoc for functions
/**
 * Call API to sanitize text
 * @param {string} text - Input text
 * @param {string} mode - Operation mode
 * @returns {Promise<Object>} API response
 */
async function callAPI(text, mode) {}
```

### CSS Style

```css
/* Use CSS custom properties */
:root {
  --accent-color: #00ffff;
  --bg-color: #0a0a0a;
}

/* Class names: kebab-case */
.input-panel {}
.output-panel {}

/* Spacing: 2 spaces indentation */
.container {
  display: grid;
  gap: 20px;
}

/* Group related properties */
.element {
  /* Positioning */
  position: relative;
  top: 0;

  /* Box model */
  width: 100%;
  padding: 20px;

  /* Typography */
  font-size: 16px;
  color: #fff;

  /* Visual */
  background: var(--bg-color);
  border-radius: 5px;
}

/* Comments for complex sections */
/* Animated starfield background */
@keyframes starfield {
  /* ... */
}
```

## Common Tasks

### Task 1: Update Version Number

1. Edit `api/TextLinter.php`:

```php
const VERSION = '2.3.0'; // Update version
```

2. Update README files
3. Create git tag:

```bash
git tag -a v2.3.0 -m "Release version 2.3.0"
git push origin v2.3.0
```

### Task 2: Add New Test Case

1. Edit `tests/test-samples.txt`:

```
# Test Case XX: Description
Input: [input]
Expected (safe): [output]
Expected (aggressive): [output]
Expected (strict): [output]
```

2. Test manually via UI
3. Update `smoke-test.sh` if automated test needed

### Task 3: Update Dependencies

```bash
# Check PHP version
php -v

# Check extension versions
php -r "echo IntlChar::getUnicodeVersion();"

# Update composer (if used)
composer update

# Test after updates
./tests/smoke-test.sh
```

### Task 4: Debug Production Issue

1. Enable error logging (see [Debugging](#debugging))
2. Reproduce issue locally
3. Add debug output
4. Check server error logs
5. Fix and test
6. Deploy fix
7. Verify in production

## Next Steps

- Read [ARCHITECTURE.md](ARCHITECTURE.md) for system design
- Review [API_REFERENCE.md](API_REFERENCE.md) for API details
- Check [SECURITY.md](SECURITY.md) for security considerations
- See [CONTRIBUTING.md](CONTRIBUTING.md) for contribution guidelines

---

**Questions?** Open an issue on GitHub or contact the maintainers.
