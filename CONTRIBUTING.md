# Contributing to Cosmic Text Linter

Thank you for your interest in contributing to the Cosmic Text Linter! This document provides guidelines and instructions for contributing to the project.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [How Can I Contribute?](#how-can-i-contribute)
- [Getting Started](#getting-started)
- [Development Process](#development-process)
- [Coding Standards](#coding-standards)
- [Testing Requirements](#testing-requirements)
- [Pull Request Process](#pull-request-process)
- [Issue Guidelines](#issue-guidelines)
- [Community](#community)

## Code of Conduct

### Our Pledge

We are committed to providing a welcoming and inspiring community for all. Please be respectful and constructive in all interactions.

### Our Standards

**Positive behavior includes:**

- Using welcoming and inclusive language
- Being respectful of differing viewpoints
- Gracefully accepting constructive criticism
- Focusing on what is best for the community
- Showing empathy towards other community members

**Unacceptable behavior includes:**

- Trolling, insulting/derogatory comments, and personal attacks
- Public or private harassment
- Publishing others' private information without permission
- Other conduct which could reasonably be considered inappropriate

### Enforcement

Violations of the Code of Conduct should be reported to the project maintainers. All complaints will be reviewed and investigated promptly and fairly.

## How Can I Contribute?

### 🐛 Reporting Bugs

Before creating bug reports, please check the [existing issues](https://github.com/johnzfitch/definitelynot.ai/issues) to avoid duplicates.

**When submitting a bug report, include:**

- **Clear title and description**
- **Steps to reproduce** the issue
- **Expected behavior** vs. **actual behavior**
- **Screenshots** if applicable
- **Environment details**:
  - PHP version (`php -v`)
  - PHP extensions (`php -m`)
  - Unicode/ICU version (`php -r "echo IntlChar::getUnicodeVersion();"`)
  - Browser and version (for frontend issues)
  - Operating system
- **Sample input** that triggers the bug

**Example:**

```markdown
### Bug: Emoji flag incorrectly stripped in Safe mode

**Steps to Reproduce:**
1. Open web interface
2. Enter text: "Hello 🇺🇸 world"
3. Select "Safe" mode
4. Click "Sanitize Text"

**Expected:** Emoji flag preserved
**Actual:** Emoji flag removed

**Environment:**
- PHP 7.4.33
- ICU Unicode 15.0
- Chrome 120.0.6099.109
- macOS 14.0

**Input:** `"Hello 🇺🇸 world"`
**Output:** `"Hello  world"`
```

### 💡 Suggesting Enhancements

Enhancement suggestions are welcome! Please provide:

- **Clear use case** - Why is this enhancement needed?
- **Detailed description** - How should it work?
- **Examples** - Show input/output examples
- **Alternatives considered** - What other approaches did you consider?
- **Impact assessment** - Performance, security, compatibility implications

**Example:**

```markdown
### Enhancement: Add "moderate" operation mode

**Use Case:**
Users need a middle ground between "safe" (too permissive) and "aggressive" (too strict) for content moderation.

**Description:**
Add a new "moderate" mode that:
- Preserves emoji and common Latin diacritics
- Removes BiDi controls and zero-width characters
- Detects but doesn't normalize homoglyphs
- Uses NFC normalization (not NFKC)

**Examples:**
Input: "Café 👋 Hеllo"
Output (moderate): "Café 👋 Hello" (Cyrillic е → Latin e)

**Alternatives:**
- Configurable mode with custom rules (more complex)
- Multiple sub-modes (ui complexity)

**Impact:**
- Low performance impact (similar to safe mode)
- No breaking changes (new mode)
- Requires UI update and documentation
```

### 📝 Improving Documentation

Documentation improvements are always appreciated:

- Fix typos or clarify explanations
- Add examples or code snippets
- Improve diagrams or visualizations
- Translate documentation to other languages
- Add missing API documentation

### 🔧 Contributing Code

See [Development Process](#development-process) below.

## Getting Started

### Prerequisites

- **Git** - Version control
- **PHP 7.4+** - With `mbstring`, `intl`, `json` extensions
- **Web server** - Apache 2.4+ or Nginx 1.18+
- **Text editor** - VSCode, PhpStorm, or similar
- **Basic knowledge** of:
  - PHP (OOP, static methods)
  - JavaScript (ES6+, async/await)
  - HTML/CSS (semantic markup, grid layout)
  - Unicode concepts (normalization, scripts, BiDi)

### Initial Setup

1. **Fork the repository** on GitHub

2. **Clone your fork:**

```bash
git clone https://github.com/YOUR_USERNAME/definitelynot.ai.git
cd definitelynot.ai
```

3. **Add upstream remote:**

```bash
git remote add upstream https://github.com/johnzfitch/definitelynot.ai.git
```

4. **Verify PHP setup:**

```bash
php -v
php -m | grep -E 'mbstring|intl|json'
php -r "echo IntlChar::getUnicodeVersion();"
```

5. **Start development server:**

```bash
cd cosmic-text-linter
php -S localhost:8000
```

6. **Run tests:**

```bash
cd tests
chmod +x smoke-test.sh
API_URL="http://localhost:8000/api/clean.php" ./smoke-test.sh
```

## Development Process

### 1. Create a Branch

Always create a new branch for your work:

```bash
# Update your fork
git checkout main
git pull upstream main

# Create feature branch
git checkout -b feature/your-feature-name

# Or bugfix branch
git checkout -b bugfix/issue-description
```

**Branch naming conventions:**

- `feature/description` - New features
- `bugfix/description` - Bug fixes
- `docs/description` - Documentation changes
- `refactor/description` - Code refactoring
- `perf/description` - Performance improvements
- `test/description` - Test additions/improvements

### 2. Make Changes

- Write clean, well-documented code
- Follow the [Coding Standards](#coding-standards)
- Add tests for new functionality
- Update documentation as needed

### 3. Test Your Changes

```bash
# Run automated tests
cd tests
./smoke-test.sh

# Manual testing via web interface
# Test all three modes: safe, aggressive, strict
# Test edge cases from test-samples.txt

# Check PHP syntax
find cosmic-text-linter/api -name "*.php" -exec php -l {} \;

# Check JavaScript syntax (if you have Node.js)
npx eslint cosmic-text-linter/assets/js/script.js
```

### 4. Commit Changes

Write clear, descriptive commit messages:

```bash
git add .
git commit -m "Add feature: description

- Detailed change 1
- Detailed change 2
- Fixes #issue-number"
```

**Commit message format:**

```
<type>: <subject>

<body>

<footer>
```

**Types:**
- `feat:` - New feature
- `fix:` - Bug fix
- `docs:` - Documentation changes
- `style:` - Code style changes (formatting, no logic change)
- `refactor:` - Code refactoring
- `perf:` - Performance improvements
- `test:` - Test additions or modifications
- `chore:` - Maintenance tasks

**Example:**

```
feat: Add moderate operation mode

- Implemented new "moderate" mode between safe and aggressive
- Preserves emoji and common diacritics
- Normalizes homoglyphs without removing them
- Added UI option in mode selector
- Updated API documentation

Closes #42
```

### 5. Push and Create Pull Request

```bash
# Push to your fork
git push origin feature/your-feature-name

# Create pull request on GitHub
# Visit: https://github.com/YOUR_USERNAME/definitelynot.ai/pull/new/feature/your-feature-name
```

## Coding Standards

### PHP Standards

```php
<?php
declare(strict_types=1);

/**
 * Class description
 */
class TextLinter
{
    /** @var string Version constant */
    const VERSION = '2.2.1';

    /**
     * Method description
     *
     * @param string $text Input text
     * @param string $mode Operation mode
     * @return array Result array
     */
    public static function clean(string $text, string $mode = 'safe'): array
    {
        // Use descriptive variable names
        $originalLength = mb_strlen($text);

        // Comments for complex logic
        // Remove BiDi controls (CVE-2021-42574 mitigation)
        $text = preg_replace('/[\x{202A}-\x{202E}]/u', '', $text);

        return [
            'text' => $text,
            'stats' => [/* ... */]
        ];
    }

    /**
     * Private helper method
     *
     * @param string $text Input
     * @return string Processed text
     */
    private static function helperMethod(string $text): string
    {
        // Implementation
    }
}
```

**PHP Style Rules:**

- Indentation: 4 spaces
- Line length: Max 120 characters
- Braces: Opening brace on same line for methods/classes
- Type hints: Always use for parameters and return types
- Documentation: PHPDoc for all public methods
- Naming:
  - Classes: `PascalCase`
  - Methods: `camelCase`
  - Constants: `UPPER_SNAKE_CASE`
  - Variables: `camelCase`

### JavaScript Standards

```javascript
/**
 * Function description
 * @param {string} text - Input text
 * @param {string} mode - Operation mode
 * @returns {Promise<Object>} API response
 */
async function callAPI(text, mode) {
  // Use const/let, not var
  const API_URL = '/api/clean.php';

  // Async/await for promises
  try {
    const response = await fetch(API_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ text, mode })
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    return await response.json();
  } catch (error) {
    console.error('API call failed:', error);
    throw error;
  }
}

// Use arrow functions for callbacks
document.querySelectorAll('.btn').forEach(btn => {
  btn.addEventListener('click', handleClick);
});
```

**JavaScript Style Rules:**

- Indentation: 2 spaces
- Line length: Max 100 characters
- Semicolons: Always use
- Quotes: Single quotes for strings
- Variables: `const` by default, `let` when reassignment needed
- Functions: Use `async/await` instead of `.then()`
- Documentation: JSDoc for all functions
- Naming:
  - Functions: `camelCase`
  - Classes: `PascalCase`
  - Constants: `UPPER_SNAKE_CASE`

### CSS Standards

```css
/* Use custom properties */
:root {
  --accent-color: #00ffff;
  --spacing-unit: 8px;
}

/* Class naming: kebab-case */
.input-panel {
  /* Group properties logically */

  /* Positioning */
  position: relative;
  z-index: 10;

  /* Box model */
  display: grid;
  gap: var(--spacing-unit);
  padding: calc(var(--spacing-unit) * 2);

  /* Typography */
  font-family: 'Space Mono', monospace;
  font-size: 16px;

  /* Visual */
  background: rgba(0, 0, 0, 0.8);
  border-radius: 8px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
}

/* Comments for complex sections */
/* Animated starfield effect using pseudo-elements */
.starfield::before {
  /* ... */
}
```

**CSS Style Rules:**

- Indentation: 2 spaces
- Property order: Positioning → Box model → Typography → Visual
- Selectors: Use classes, avoid IDs
- Naming: `kebab-case`
- Units: Use relative units (`rem`, `em`, `%`) when possible
- Colors: Use CSS custom properties
- Specificity: Keep low, avoid `!important`

## Testing Requirements

All contributions must include appropriate tests.

### Backend Tests

Add test cases to `tests/test-samples.txt`:

```
# Test Case: Your Feature
# Category: [Security/Formatting/Normalization]
Input: [Input with Unicode escapes if needed]
Expected (safe): [Output in safe mode]
Expected (aggressive): [Output in aggressive mode]
Expected (strict): [Output in strict mode]
Notes: [Additional context]
```

Add automated test to `tests/smoke-test.sh`:

```bash
test_your_feature() {
  local description="Your feature description"
  local input='{"text":"input text","mode":"safe"}'
  local expected="expected output\n"

  local response=$(curl -s -X POST "$API_URL" \
    -H 'Content-Type: application/json' \
    -d "$input")

  local result=$(echo "$response" | jq -r '.text')

  if [ "$result" == "$expected" ]; then
    echo "✓ Test: $description passed"
  else
    echo "✗ Test: $description failed"
    echo "  Expected: $expected"
    echo "  Got: $result"
    exit 1
  fi
}
```

### Frontend Tests

Manual testing checklist:

- [ ] Feature works in Chrome/Edge
- [ ] Feature works in Firefox
- [ ] Feature works in Safari
- [ ] Mobile responsive (test at 375px, 768px, 1024px)
- [ ] Keyboard accessible (Tab, Enter, Esc)
- [ ] Screen reader friendly (test with VoiceOver/NVDA)
- [ ] No console errors
- [ ] Performance acceptable (< 100ms for UI updates)

### Running Tests

```bash
# Backend tests
cd tests
./smoke-test.sh

# Check for syntax errors
find cosmic-text-linter -name "*.php" -exec php -l {} \;

# Manual testing
# 1. Test all three modes
# 2. Test sample inputs from test-samples.txt
# 3. Test edge cases (empty input, max size, invalid UTF-8)
# 4. Test error handling (400, 413, 422, 500)
```

## Pull Request Process

### Before Submitting

- [ ] Code follows style guidelines
- [ ] All tests pass
- [ ] Documentation updated
- [ ] Commit messages are clear
- [ ] Branch is up to date with upstream main

```bash
# Sync with upstream
git fetch upstream
git rebase upstream/main

# Resolve any conflicts
# ... fix conflicts ...
git add .
git rebase --continue

# Force push to your fork
git push origin feature/your-feature-name --force
```

### Pull Request Template

When creating a PR, use this template:

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix (non-breaking change which fixes an issue)
- [ ] New feature (non-breaking change which adds functionality)
- [ ] Breaking change (fix or feature that would cause existing functionality to change)
- [ ] Documentation update

## Related Issue
Fixes #(issue number)

## Changes Made
- Change 1
- Change 2
- Change 3

## Testing
Describe testing performed:
- [ ] Automated tests pass
- [ ] Manual testing completed
- [ ] Edge cases covered

## Screenshots (if applicable)
[Add screenshots for UI changes]

## Checklist
- [ ] Code follows project style guidelines
- [ ] Self-review completed
- [ ] Comments added for complex code
- [ ] Documentation updated
- [ ] No new warnings generated
- [ ] Tests added/updated
- [ ] All tests pass
```

### Review Process

1. **Automated checks** run (syntax validation, tests)
2. **Maintainer review** - Code quality, design, tests
3. **Feedback** - Requested changes or approval
4. **Revisions** - Address feedback, push updates
5. **Approval** - PR approved by maintainer
6. **Merge** - Merged to main branch

### After Merge

- Delete your feature branch
- Update your local repository
- Celebrate! 🎉

```bash
git checkout main
git pull upstream main
git branch -d feature/your-feature-name
git push origin --delete feature/your-feature-name
```

## Issue Guidelines

### Creating Issues

Use the appropriate template:

**Bug Report Template:**
```markdown
**Describe the bug**
Clear description of the issue

**To Reproduce**
1. Step 1
2. Step 2
3. See error

**Expected behavior**
What should happen

**Screenshots**
If applicable

**Environment**
- PHP version:
- ICU version:
- Browser:
- OS:

**Additional context**
Any other relevant information
```

**Feature Request Template:**
```markdown
**Is your feature request related to a problem?**
Description of the problem

**Describe the solution you'd like**
Clear description of desired functionality

**Describe alternatives you've considered**
Other approaches considered

**Additional context**
Any other relevant information
```

### Issue Labels

- `bug` - Something isn't working
- `enhancement` - New feature or request
- `documentation` - Documentation improvements
- `good first issue` - Good for newcomers
- `help wanted` - Extra attention needed
- `question` - Further information requested
- `wontfix` - This will not be worked on
- `duplicate` - This issue already exists
- `invalid` - This doesn't seem right

## Community

### Communication Channels

- **GitHub Issues** - Bug reports, feature requests
- **GitHub Discussions** - General questions, ideas
- **Pull Requests** - Code contributions

### Getting Help

- Read the [README](README.md) and [documentation](cosmic-text-linter/README.md)
- Check [existing issues](https://github.com/johnzfitch/definitelynot.ai/issues)
- Review [Developer Guide](DEVELOPER_GUIDE.md)
- Ask in [GitHub Discussions](https://github.com/johnzfitch/definitelynot.ai/discussions)

### Recognition

Contributors are recognized in:
- GitHub contributors page
- Project documentation
- Release notes

## License

By contributing, you agree that your contributions will be licensed under the same [MIT License](LICENSE) that covers the project.

---

**Thank you for contributing to Cosmic Text Linter!** 🚀

Your contributions help make Unicode security accessible to everyone.

---

*Questions about contributing? Open a [discussion](https://github.com/johnzfitch/definitelynot.ai/discussions) or contact the maintainers.*
