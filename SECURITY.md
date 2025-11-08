# Security Documentation

> Comprehensive security guide for the Cosmic Text Linter project

## Table of Contents

- [Security Model](#security-model)
- [Threat Landscape](#threat-landscape)
- [Defense Mechanisms](#defense-mechanisms)
- [Security Best Practices](#security-best-practices)
- [Vulnerability Reporting](#vulnerability-reporting)
- [Security Advisories](#security-advisories)
- [Deployment Security](#deployment-security)
- [Compliance](#compliance)

## Security Model

The Cosmic Text Linter implements a **defense-in-depth** security strategy with multiple layers of protection:

```
┌─────────────────────────────────────────────────────────┐
│              Layer 1: Transport Security                 │
│  HTTPS/TLS • CORS • CSP • Security Headers              │
├─────────────────────────────────────────────────────────┤
│              Layer 2: Input Validation                   │
│  Size Limits • Content-Type • JSON Schema               │
├─────────────────────────────────────────────────────────┤
│              Layer 3: Sanitization Pipeline              │
│  16-Step Unicode Processing • Mode-Based Rules          │
├─────────────────────────────────────────────────────────┤
│              Layer 4: Threat Detection                   │
│  Advisory System • ICU Spoofchecker                     │
├─────────────────────────────────────────────────────────┤
│              Layer 5: Output Encoding                    │
│  UTF-8 Validation • JSON Encoding • Safe Rendering      │
└─────────────────────────────────────────────────────────┘
```

### Security Principles

1. **Fail Secure**: Errors default to safe behavior
2. **Least Privilege**: Minimal permissions required
3. **Defense in Depth**: Multiple security layers
4. **Input Validation**: All inputs validated and sanitized
5. **Output Encoding**: All outputs properly encoded
6. **Security by Design**: Security built-in, not bolted-on

## Threat Landscape

The Cosmic Text Linter defends against Unicode-based security vulnerabilities:

### 1. Trojan Source (CVE-2021-42574)

**Threat**: Bidirectional text attacks that make code appear different than its actual execution.

**Example:**
```
// Appears as: access = "user"
// Actually is: access = "admin" /* user */
access_level = "user‮ ⁦// Check if admin⁩ ⁦if (isAdmin)⁩ ⁦ {admin⁦"
```

**Characters**:
- U+202A (LRE) - Left-to-Right Embedding
- U+202B (RLE) - Right-to-Left Embedding
- U+202C (PDF) - Pop Directional Formatting
- U+202D (LRO) - Left-to-Right Override
- U+202E (RLO) - Right-to-Left Override
- U+2066 (LRI) - Left-to-Right Isolate
- U+2067 (RLI) - Right-to-Left Isolate
- U+2068 (FSI) - First Strong Isolate
- U+2069 (PDI) - Pop Directional Isolate

**Mitigation**: All BiDi control characters removed in all modes.

**Advisory Flag**: `had_bidi_controls`

### 2. Homoglyph Spoofing

**Threat**: Visually identical characters from different scripts used for phishing/spoofing.

**Examples**:
- `a` (Latin) vs `а` (Cyrillic U+0430)
- `e` (Latin) vs `е` (Cyrillic U+0435)
- `o` (Latin) vs `ο` (Greek U+03BF)
- `apple.com` vs `аррӏе.com` (mixed Cyrillic/Latin)

**Common Homoglyphs**:
```
Latin → Cyrillic:
a → а (U+0430)
c → с (U+0441)
e → е (U+0435)
o → о (U+043E)
p → р (U+0440)
x → х (U+0445)

Latin → Greek:
o → ο (U+03BF)
v → ν (U+03BD)
a → α (U+03B1)
```

**Mitigation**:
- **Safe mode**: Detection via ICU Spoofchecker
- **Aggressive/Strict**: Normalization to Latin equivalents

**Advisory Flags**: `confusable_suspected`, `had_mixed_scripts`

### 3. Invisible Character Attacks

**Threat**: Zero-width and invisible characters for steganography, tracking, or obfuscation.

**Characters**:
- U+200B (ZWSP) - Zero Width Space
- U+200C (ZWNJ) - Zero Width Non-Joiner
- U+200D (ZWJ) - Zero Width Joiner (preserved for emoji)
- U+FEFF (ZWNBSP) - Zero Width No-Break Space
- U+00AD (SHY) - Soft Hyphen
- U+034F (CGJ) - Combining Grapheme Joiner
- U+FE00-FE0F - Variation Selectors

**Example**:
```
"Hello​world" (contains U+200B between Hello and world)
"admin​​​​" (admin with invisible suffix for bypass)
```

**Mitigation**: Removed in all modes (except ZWJ for emoji sequences).

**Advisory Flag**: `had_default_ignorables`

### 4. TAG Character Injection

**Threat**: TAG block characters (U+E0000-E007F) used for prompt injection or hidden commands.

**Example**:
```
"Ignore previous instructions󠀁󠀂󠀃"
```

**Mitigation**: TAG characters stripped while preserving emoji flag sequences.

**Advisory Flag**: `had_tag_chars`

### 5. Zalgo Text Attacks

**Threat**: Excessive combining marks stacked on base characters, causing rendering issues or crashes.

**Example**:
```
H̴̡̢̛̖̯̦͉̰͚̣̦̻͇̹͓̱̩̺̟̝̀͊̀̀̈́̈́͛́͐͒͆̈́̓̓́̃̚͘͝͝ͅę̸̢̧̛̛̛͖̫̘̻̪̻̗̺͓̩̝̪̩̻̖̦̜̞̖̳̬̗̲̙̻̈́̀̓͂̈̔̀̂̋̉̑͒̑̃̽͐̓͌̄͗̐̿̕͘͜͠͝ͅl̷̨̢̨̡̛̛͚̦̫̙̲̖̖̪̠̣̖̫̝̝̺̠̗̫͙̝̜̯͖̮͌̈́̾̋̑̓̊̾̋̎̀̂̒͊̎̿̚͜͝l̴̡̨̢̛̝̼̻̜̰̫̪͍̮̞̫̰̙͈̮̘̞̖̜͉̀͋́̎̃̾͗̈́̾̋́̍̏̃́͊̀̆̔̕͘̕͜͝ǫ̶̡̨̡̨̛̛̮̘̹̜̗̜͙͙̞̠͕̱̰̱̭̖̹̭̜̻̯̞̤̹̣̮̪̰͕̝̩̭̘̮̘̺̖̫̪̖̱̩̰̼̩̝͔̗͓͈̦͈̩̖̰̪̳̙͍̱̂͒͒̈̊̒̃͆͌̈̆̀̌̏̈́̈̋̋͘͜͜͝
```

**Mitigation**: Limit combining marks per base character (1-3 depending on mode).

**Advisory Flag**: `had_orphan_combining`

### 6. Mixed Script Confusion

**Threat**: Mixing multiple scripts in identifiers or domain names for confusion attacks.

**Example**:
```
"paypal.com" (all Latin)
"pаypal.com" (Cyrillic а)
"раypal.com" (Cyrillic р and а)
```

**Mitigation**: Detection in all modes, normalization in aggressive/strict.

**Advisory Flag**: `had_mixed_scripts`

### 7. Digit Spoofing

**Threat**: Non-ASCII digits that look similar but have different values.

**Examples**:
- Arabic-Indic: ٠١٢٣٤٥٦٧٨٩ (U+0660-0669)
- Extended Arabic-Indic: ۰۱۲۳۴۵۶۷۸۹ (U+06F0-06F9)
- Devanagari: ०१२३४५६७८९ (U+0966-096F)

**Example**:
```
"Price: $٥٠٠" (Arabic-Indic 500)
"Amount: ۱۰۰۰" (Extended Arabic-Indic 1000)
```

**Mitigation**: Normalized to ASCII digits in aggressive/strict modes.

**Advisory Flag**: `had_non_ascii_digits`

### 8. Noncharacter Exploitation

**Threat**: Noncharacter code points (U+FDD0-FDEF, U+FFFE, U+FFFF) used for internal processing or attacks.

**Mitigation**: Removed in aggressive/strict modes.

**Advisory Flag**: `had_noncharacters`

### 9. Private Use Area (PUA) Covert Channels

**Threat**: PUA characters (U+E000-F8FF, U+F0000-FFFFD, U+100000-10FFFD) used for hidden data transmission.

**Mitigation**: Removed in strict mode only.

**Advisory Flag**: `had_private_use`

### 10. HTML Entity Encoding

**Threat**: HTML entities used to obfuscate malicious content.

**Examples**:
- `&lt;script&gt;` → `<script>`
- `&nbsp;` → non-breaking space
- `&mdash;` → em dash

**Mitigation**: Decoded and normalized in step 1.

**Advisory Flag**: `had_html_entities`

## Defense Mechanisms

### 1. Sanitization Pipeline (16 Steps)

Each step targets specific threats:

| Step | Defense Against | Mode Applicability |
|------|-----------------|-------------------|
| 1. HTML Entity Decode | HTML entity obfuscation | All |
| 2. ASCII Control Removal | Control character injection | All |
| 3. BiDi Control Removal | Trojan Source (CVE-2021-42574) | All |
| 4. Unicode Normalization | Canonicalization attacks | All |
| 5. Invisible Char Removal | Zero-width steganography | All |
| 6. Whitespace Normalization | Whitespace confusion | All |
| 7. Digit Normalization | Digit spoofing | Aggressive/Strict |
| 8. Punctuation Normalization | Punctuation confusion | All |
| 9. Combining Mark Cleanup | Zalgo text | All |
| 10. Formatting Removal | Markdown/HTML injection | All |
| 11. Noncharacter Removal | Noncharacter exploitation | Aggressive/Strict |
| 12. PUA Removal | Covert channels | Strict only |
| 13. TAG Block Removal | TAG injection | All |
| 14. Homoglyph Normalization | Homoglyph spoofing | Aggressive/Strict |
| 15. Spoof Audit | ICU confusable detection | All |
| 16. Mirrored Punctuation | RTL confusion | All |

### 2. Mode-Based Security Profiles

#### Safe Mode
- **Use Case**: Multilingual content, social media, general text
- **Philosophy**: Detect but preserve
- **Normalization**: NFC (Canonical Composition)
- **Preserves**: Emoji, RTL marks, non-Latin scripts
- **Removes**: BiDi controls, invisibles, ASCII controls
- **Best For**: User-facing content with legitimate multilingual use

#### Aggressive Mode
- **Use Case**: User-generated content, comments, forums
- **Philosophy**: Latin-preferred, strip risky formatting
- **Normalization**: NFC
- **Preserves**: Latin + limited diacritics, emoji
- **Removes**: Homoglyphs, noncharacters, most formatting
- **Best For**: Content moderation platforms

#### Strict Mode
- **Use Case**: Security-critical contexts, code review, identifiers
- **Philosophy**: Maximum security, ASCII-preferred
- **Normalization**: NFKC + case folding
- **Preserves**: ASCII + safe Latin subset
- **Removes**: PUA, all confusables, formatting
- **Best For**: Source code scanning, security analysis

### 3. Advisory System

Real-time threat detection reporting:

```json
{
  "advisories": {
    "had_bidi_controls": true,        // ⚠️ Trojan Source detected
    "had_mixed_scripts": true,         // ⚠️ Script mixing detected
    "confusable_suspected": true,      // ⚠️ Homoglyphs detected
    "had_default_ignorables": true,    // ⚠️ Invisible chars found
    "had_tag_chars": false,            // ✓ No TAG injection
    "had_orphan_combining": false      // ✓ No Zalgo text
  }
}
```

### 4. Input Validation

Multiple validation layers:

```php
// Size limit: 1 MB
if (strlen($rawInput) > 1048576) {
    http_response_code(413);
    exit(json_encode(['error' => 'Payload too large']));
}

// Content-Type validation
if (strpos($_SERVER['CONTENT_TYPE'], 'application/json') === false) {
    http_response_code(415);
    exit(json_encode(['error' => 'Invalid Content-Type']));
}

// UTF-8 validation
if (!mb_check_encoding($text, 'UTF-8')) {
    $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
}
```

## Security Best Practices

### For Developers

#### 1. Always Validate User Input

```php
// Bad: Direct use of user input
$name = $_POST['name'];
echo "Hello, $name";

// Good: Sanitize first
$name = TextLinter::clean($_POST['name'], 'aggressive')['text'];
echo "Hello, " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
```

#### 2. Choose Appropriate Mode

```php
// User profile names: Safe mode
$displayName = TextLinter::clean($input, 'safe')['text'];

// Comment content: Aggressive mode
$comment = TextLinter::clean($input, 'aggressive')['text'];

// Code identifiers: Strict mode
$variableName = TextLinter::clean($input, 'strict')['text'];
```

#### 3. Check Advisories

```php
$result = TextLinter::clean($input, 'safe');

if ($result['stats']['advisories']['had_bidi_controls']) {
    // Log security event
    error_log("BiDi controls detected in input from user $userId");

    // Consider rejecting or flagging content
    $flagged = true;
}

if ($result['stats']['advisories']['confusable_suspected']) {
    // Alert moderation team
    notify_moderators($userId, $input);
}
```

#### 4. Implement Rate Limiting

```nginx
# Nginx rate limiting
http {
    limit_req_zone $binary_remote_addr zone=api:10m rate=100r/m;

    server {
        location /api/ {
            limit_req zone=api burst=20 nodelay;
        }
    }
}
```

#### 5. Enable HTTPS Only

```apache
# .htaccess - Force HTTPS
RewriteEngine On
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### For Deployments

#### 1. Configure Security Headers

```apache
# .htaccess security headers
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"
Header set Referrer-Policy "strict-origin-when-cross-origin"
Header set Permissions-Policy "geolocation=(), microphone=(), camera=()"

# Content Security Policy
Header set Content-Security-Policy "default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self'; img-src 'self' data:; connect-src 'self'"
```

#### 2. Restrict CORS

```apache
# Production CORS (replace * with your domain)
Header set Access-Control-Allow-Origin "https://yourdomain.example"
Header set Access-Control-Allow-Methods "POST, OPTIONS"
Header set Access-Control-Allow-Headers "Content-Type"
Header set Access-Control-Max-Age "3600"
```

#### 3. Set PHP Security Limits

```apache
# Resource limits
php_value upload_max_filesize 2M
php_value post_max_size 2M
php_value memory_limit 128M
php_value max_execution_time 30
php_value max_input_time 30
```

#### 4. Disable Directory Listing

```apache
Options -Indexes
```

#### 5. Protect Sensitive Files

```apache
# Deny access to config, logs, tests
<FilesMatch "\.(md|txt|log|sh)$">
    Require all denied
</FilesMatch>

<Files ".htaccess">
    Require all denied
</Files>
```

## Vulnerability Reporting

### Reporting Security Issues

**DO NOT** open public GitHub issues for security vulnerabilities.

Instead, please report security vulnerabilities via:

1. **Email**: security@internetuniverse.org (preferred)
2. **GitHub Security Advisory**: Use the "Report a vulnerability" button
3. **Encrypted Email**: PGP key available on request

### What to Include

- **Description** of the vulnerability
- **Steps to reproduce** the issue
- **Affected versions**
- **Potential impact** assessment
- **Suggested fix** if available
- **Your contact information** for follow-up

### Response Timeline

- **Acknowledgment**: Within 48 hours
- **Initial assessment**: Within 7 days
- **Fix development**: Within 30 days (severity-dependent)
- **Public disclosure**: After fix is deployed and users notified

### Responsible Disclosure

We follow coordinated vulnerability disclosure:

1. Security researcher reports vulnerability privately
2. We acknowledge and investigate
3. We develop and test a fix
4. We notify affected users
5. We publish security advisory
6. We credit the researcher (if desired)

## Security Advisories

### Published Advisories

None at this time.

### Security Update Policy

- **Critical vulnerabilities**: Patch released within 7 days
- **High severity**: Patch released within 30 days
- **Medium severity**: Patch released within 90 days
- **Low severity**: Included in next regular release

### Version Support

| Version | Supported | End of Life |
|---------|-----------|-------------|
| 2.2.x   | ✅ Yes     | TBD         |
| 2.1.x   | ⚠️ Limited | 2025-12-31  |
| 2.0.x   | ❌ No      | 2024-12-31  |
| < 2.0   | ❌ No      | 2024-01-01  |

## Deployment Security

### Pre-Deployment Checklist

- [ ] HTTPS/TLS enabled and enforced
- [ ] Security headers configured
- [ ] CORS restricted to production domain
- [ ] PHP extensions verified (`mbstring`, `intl`)
- [ ] Resource limits configured
- [ ] Rate limiting enabled
- [ ] Error logging enabled (but not displayed to users)
- [ ] Directory listing disabled
- [ ] Sensitive files protected (.htaccess, .git, etc.)
- [ ] Regular backups configured
- [ ] Monitoring and alerting set up

### Hardening Guide

#### 1. Disable PHP Information Disclosure

```php
# php.ini or .htaccess
expose_php = Off
display_errors = Off
log_errors = On
```

#### 2. Enable PHP OpCache

```ini
opcache.enable=1
opcache.validate_timestamps=0  # Production only
```

#### 3. Implement Web Application Firewall

Consider using:
- ModSecurity (Apache)
- NAXSI (Nginx)
- Cloudflare WAF
- AWS WAF

#### 4. Monitor Security Events

Log and alert on:
- High advisory counts
- Repeated BiDi control detection
- Unusual request patterns
- Failed requests (400, 413, 422)
- High volume from single IP

## Compliance

### OWASP Top 10 Coverage

| Risk | Coverage | Notes |
|------|----------|-------|
| A01:2021 – Broken Access Control | N/A | No authentication/authorization |
| A02:2021 – Cryptographic Failures | ✅ Covered | HTTPS enforcement, secure headers |
| A03:2021 – Injection | ✅ Covered | Full Unicode sanitization |
| A04:2021 – Insecure Design | ✅ Covered | Security-by-design architecture |
| A05:2021 – Security Misconfiguration | ✅ Covered | Secure defaults, hardening guide |
| A06:2021 – Vulnerable Components | ✅ Covered | Minimal dependencies, regular updates |
| A07:2021 – Identification/Authentication | N/A | No auth required |
| A08:2021 – Software/Data Integrity | ✅ Covered | Input validation, integrity checks |
| A09:2021 – Logging/Monitoring Failures | ⚠️ Partial | Advisory system, deployment-dependent logging |
| A10:2021 – Server-Side Request Forgery | N/A | No external requests |

### CWE Coverage

- **CWE-20**: Improper Input Validation ✅
- **CWE-79**: Cross-site Scripting (XSS) ✅
- **CWE-94**: Code Injection ✅
- **CWE-116**: Improper Encoding or Escaping ✅
- **CWE-176**: Improper Handling of Unicode Encoding ✅
- **CWE-838**: Inappropriate Encoding for Output Context ✅

### GDPR Compliance

- **Data Minimization**: No persistent data storage
- **Privacy by Design**: Stateless architecture
- **Right to be Forgotten**: Not applicable (no data retention)
- **Data Portability**: Not applicable (no user accounts)
- **Transparency**: Open source, documented processing

## References

### Unicode Security Standards

- [UTS #39](https://unicode.org/reports/tr39/) - Unicode Security Mechanisms
- [UAX #31](https://unicode.org/reports/tr31/) - Unicode Identifier and Pattern Syntax
- [UAX #9](https://unicode.org/reports/tr9/) - Unicode Bidirectional Algorithm
- [UTS #51](https://unicode.org/reports/tr51/) - Unicode Emoji

### CVEs and Security Research

- [CVE-2021-42574](https://cve.mitre.org/cgi-bin/cvename.cgi?name=CVE-2021-42574) - Trojan Source
- [CVE-2021-42694](https://cve.mitre.org/cgi-bin/cvename.cgi?name=CVE-2021-42694) - Homoglyph Attacks

### Security Best Practices

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [CWE Top 25](https://cwe.mitre.org/top25/)
- [NIST Cybersecurity Framework](https://www.nist.gov/cyberframework)

---

**Last Updated**: 2025-11-08
**Security Contact**: security@internetuniverse.org

For questions about security, please contact the security team or open a [security advisory](https://github.com/johnzfitch/definitelynot.ai/security/advisories).
