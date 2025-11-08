# API Reference Documentation

> Complete reference for the Cosmic Text Linter RESTful API

## Table of Contents

- [Overview](#overview)
- [Base URL](#base-url)
- [Authentication](#authentication)
- [Endpoints](#endpoints)
- [Request Format](#request-format)
- [Response Format](#response-format)
- [Error Handling](#error-handling)
- [Rate Limiting](#rate-limiting)
- [Examples](#examples)
- [Client Libraries](#client-libraries)

## Overview

The Cosmic Text Linter provides a RESTful API for programmatic text sanitization. The API accepts JSON payloads and returns sanitized text with comprehensive statistics and security advisories.

### API Version

- **Current Version**: v2.2.1
- **Base Path**: `/api/`
- **Protocol**: HTTPS (recommended)
- **Format**: JSON
- **Encoding**: UTF-8

### Key Features

- Stateless requests (no authentication required)
- CORS-enabled for cross-origin requests
- Maximum payload size: 1 MB
- Response time: < 3 seconds (99th percentile)
- Unicode version: 15.0 (ICU-based)

## Base URL

### Production
```
https://yourdomain.example/cosmic-text-linter/api/
```

### Local Development
```
http://localhost:8000/api/
```

### cPanel Deployment
```
https://yourdomain.example/cosmic-text-linter/api/
```

## Authentication

**No authentication required.** The API is open for public use. For production deployments, consider implementing:

- API key authentication via custom header
- IP whitelisting via `.htaccess`
- Rate limiting via reverse proxy

## Endpoints

### POST /api/clean.php

Sanitizes text according to the specified operation mode.

#### URL

```
POST /api/clean.php
```

#### Headers

| Header | Value | Required |
|--------|-------|----------|
| `Content-Type` | `application/json` | Yes |
| `Accept` | `application/json` | Recommended |

#### Request Body

```json
{
  "text": "string",
  "mode": "safe|aggressive|strict"
}
```

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `text` | string | Yes | - | Text to sanitize (max 1 MB) |
| `mode` | string | No | `"safe"` | Operation mode: `safe`, `aggressive`, or `strict` |

#### Response

```json
{
  "text": "string",
  "stats": {
    "original_length": 0,
    "final_length": 0,
    "characters_removed": 0,
    "mode": "string",
    "invisibles_removed": 0,
    "homoglyphs_normalized": 0,
    "digits_normalized": 0,
    "advisories": {
      "had_bidi_controls": false,
      "had_mixed_scripts": false,
      "had_default_ignorables": false,
      "had_tag_chars": false,
      "had_orphan_combining": false,
      "confusable_suspected": false,
      "had_html_entities": false,
      "had_ascii_controls": false,
      "had_noncharacters": false,
      "had_private_use": false,
      "had_mirrored_punctuation": false,
      "had_non_ascii_digits": false
    }
  },
  "server": {
    "version": "2.2.1",
    "unicode_version": "15.0",
    "extensions": {
      "intl": true,
      "mbstring": true,
      "spoofchecker": true
    }
  }
}
```

#### Status Codes

| Code | Description |
|------|-------------|
| 200 | Success - Text sanitized |
| 400 | Bad Request - Invalid JSON |
| 413 | Payload Too Large - Exceeds 1 MB |
| 415 | Unsupported Media Type - Not `application/json` |
| 422 | Unprocessable Entity - Missing `text` field |
| 500 | Internal Server Error - PHP error |
| 503 | Service Unavailable - Missing PHP extensions |

## Request Format

### Minimal Request

```json
{
  "text": "Hello, world!"
}
```

### Complete Request

```json
{
  "text": "Hеllо\u200Bwοrld",
  "mode": "aggressive"
}
```

### Field Specifications

#### `text` (string, required)

The input text to sanitize.

- **Min Length**: 1 character
- **Max Length**: 1 MB (1,048,576 bytes)
- **Encoding**: UTF-8
- **Allowed**: Any Unicode characters
- **Newlines**: Preserved and normalized

**Examples:**
```json
// Simple text
{"text": "Hello"}

// With invisible characters
{"text": "Hello\u200Bworld"}

// With emoji
{"text": "Hello 👋 world"}

// Multiline text
{"text": "Line 1\nLine 2\nLine 3"}

// Unicode mixed scripts
{"text": "Hеllο (Cyrillic е, Greek ο)"}
```

#### `mode` (string, optional)

The sanitization mode determining aggressiveness.

- **Default**: `"safe"`
- **Values**: `"safe"`, `"aggressive"`, `"strict"`
- **Case-sensitive**: No (automatically lowercased)
- **Invalid values**: Falls back to `"safe"`

| Mode | Use Case | Preserves |
|------|----------|-----------|
| `safe` | Multilingual content, emoji | Emoji, RTL marks, non-Latin scripts |
| `aggressive` | User-generated content | ASCII + limited Latin, strips most formatting |
| `strict` | Security-critical contexts | ASCII + strict Latin, maximum normalization |

## Response Format

### Success Response (HTTP 200)

```json
{
  "text": "Sanitized output text\n",
  "stats": { ... },
  "server": { ... }
}
```

#### Response Fields

##### `text` (string)

The sanitized text output.

- **Encoding**: UTF-8
- **Trailing newline**: Always appended
- **Empty input**: Returns `"\n"`
- **Whitespace**: Normalized to single spaces/newlines

##### `stats` (object)

Sanitization statistics and metrics.

```json
{
  "original_length": 150,
  "final_length": 142,
  "characters_removed": 8,
  "mode": "safe",
  "invisibles_removed": 3,
  "homoglyphs_normalized": 2,
  "digits_normalized": 0,
  "advisories": { ... }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `original_length` | integer | Original text length (characters) |
| `final_length` | integer | Sanitized text length (characters) |
| `characters_removed` | integer | Number of characters removed |
| `mode` | string | Operation mode used |
| `invisibles_removed` | integer | Invisible/zero-width characters removed |
| `homoglyphs_normalized` | integer | Homoglyphs converted to Latin |
| `digits_normalized` | integer | Non-ASCII digits converted |

##### `stats.advisories` (object)

Security threat detection flags.

```json
{
  "had_bidi_controls": true,
  "had_mixed_scripts": false,
  "had_default_ignorables": true,
  "had_tag_chars": false,
  "had_orphan_combining": false,
  "confusable_suspected": true,
  "had_html_entities": false,
  "had_ascii_controls": false,
  "had_noncharacters": false,
  "had_private_use": false,
  "had_mirrored_punctuation": false,
  "had_non_ascii_digits": false
}
```

| Advisory | Threat | Description |
|----------|--------|-------------|
| `had_bidi_controls` | Trojan Source | BiDi override characters detected (LRE, RLE, PDF, etc.) |
| `had_mixed_scripts` | Script confusion | Multiple scripts detected (Latin + Cyrillic, etc.) |
| `had_default_ignorables` | Invisible chars | Zero-width spaces, soft hyphens, variation selectors |
| `had_tag_chars` | TAG injection | TAG block characters (U+E0000-E007F) |
| `had_orphan_combining` | Zalgo text | Combining marks without base characters |
| `confusable_suspected` | Homoglyphs | ICU Spoofchecker detected confusables |
| `had_html_entities` | Encoding issues | HTML entities like `&nbsp;`, `&mdash;` |
| `had_ascii_controls` | Control chars | ASCII control characters (C0/C1) |
| `had_noncharacters` | Noncharacters | Noncharacter code points (U+FDD0-FDEF) |
| `had_private_use` | PUA | Private Use Area characters |
| `had_mirrored_punctuation` | RTL issues | Mirrored punctuation in wrong context |
| `had_non_ascii_digits` | Digit spoofing | Arabic-Indic, Devanagari, or other non-ASCII digits |

##### `server` (object)

Server and extension information.

```json
{
  "version": "2.2.1",
  "unicode_version": "15.0",
  "extensions": {
    "intl": true,
    "mbstring": true,
    "spoofchecker": true
  }
}
```

| Field | Description |
|-------|-------------|
| `version` | TextLinter version |
| `unicode_version` | ICU Unicode database version |
| `extensions.intl` | PHP `intl` extension available |
| `extensions.mbstring` | PHP `mbstring` extension available |
| `extensions.spoofchecker` | ICU Spoofchecker class available |

### Error Response

```json
{
  "error": "Error message description"
}
```

Error responses include a single `error` field with a human-readable message.

## Error Handling

### HTTP 400 Bad Request

**Cause**: Invalid JSON syntax

```json
{
  "error": "Invalid JSON payload"
}
```

**Solution**: Ensure valid JSON syntax, proper quote escaping, UTF-8 encoding.

### HTTP 413 Payload Too Large

**Cause**: Request body exceeds 1 MB

```json
{
  "error": "Payload too large. Maximum size is 1 MB."
}
```

**Solution**: Split text into chunks < 1 MB or compress/summarize content.

### HTTP 415 Unsupported Media Type

**Cause**: Missing or incorrect `Content-Type` header

```json
{
  "error": "Content-Type must be application/json"
}
```

**Solution**: Set `Content-Type: application/json` header.

### HTTP 422 Unprocessable Entity

**Cause**: Missing required `text` field

```json
{
  "error": "Missing 'text' field in request body"
}
```

**Solution**: Include `"text": "..."` in JSON payload.

### HTTP 500 Internal Server Error

**Cause**: PHP error during processing

```json
{
  "error": "Internal server error"
}
```

**Solution**: Check server error logs, verify PHP extensions, report bug if persistent.

### HTTP 503 Service Unavailable

**Cause**: Missing required PHP extensions

```json
{
  "error": "Required PHP extensions not available: intl, mbstring"
}
```

**Solution**: Enable `intl` and `mbstring` in PHP configuration.

## Rate Limiting

Currently **no rate limiting** is enforced at the application level. For production deployments, implement rate limiting via:

### Nginx Example

```nginx
http {
    limit_req_zone $binary_remote_addr zone=api:10m rate=100r/m;

    server {
        location /api/ {
            limit_req zone=api burst=20 nodelay;
        }
    }
}
```

### Apache .htaccess Example

```apache
# Requires mod_ratelimit
<Location "/api/">
    SetOutputFilter RATE_LIMIT
    SetEnv rate-limit 400
</Location>
```

### Recommended Limits

| Environment | Rate Limit | Burst |
|-------------|------------|-------|
| Development | None | - |
| Staging | 1000/min | 50 |
| Production | 100/min | 20 |
| Enterprise | Custom | Custom |

## Examples

### Example 1: Basic Sanitization (Safe Mode)

**Request:**
```bash
curl -X POST http://localhost:8000/api/clean.php \
  -H 'Content-Type: application/json' \
  -d '{"text":"Hello\u200Bworld"}'
```

**Response:**
```json
{
  "text": "Helloworld\n",
  "stats": {
    "original_length": 11,
    "final_length": 10,
    "characters_removed": 1,
    "mode": "safe",
    "invisibles_removed": 1,
    "homoglyphs_normalized": 0,
    "digits_normalized": 0,
    "advisories": {
      "had_bidi_controls": false,
      "had_mixed_scripts": false,
      "had_default_ignorables": true,
      "had_tag_chars": false,
      "had_orphan_combining": false,
      "confusable_suspected": false,
      "had_html_entities": false,
      "had_ascii_controls": false,
      "had_noncharacters": false,
      "had_private_use": false,
      "had_mirrored_punctuation": false,
      "had_non_ascii_digits": false
    }
  },
  "server": {
    "version": "2.2.1",
    "unicode_version": "15.0",
    "extensions": {
      "intl": true,
      "mbstring": true,
      "spoofchecker": true
    }
  }
}
```

### Example 2: Homoglyph Detection (Aggressive Mode)

**Request:**
```bash
curl -X POST http://localhost:8000/api/clean.php \
  -H 'Content-Type: application/json' \
  -d '{
    "text": "Hеllο wοrld",
    "mode": "aggressive"
  }'
```

**Note**: Input contains Cyrillic 'е' (U+0435) and Greek 'ο' (U+03BF).

**Response:**
```json
{
  "text": "Hello world\n",
  "stats": {
    "original_length": 11,
    "final_length": 11,
    "characters_removed": 0,
    "mode": "aggressive",
    "invisibles_removed": 0,
    "homoglyphs_normalized": 2,
    "digits_normalized": 0,
    "advisories": {
      "had_bidi_controls": false,
      "had_mixed_scripts": true,
      "had_default_ignorables": false,
      "had_tag_chars": false,
      "had_orphan_combining": false,
      "confusable_suspected": true,
      "had_html_entities": false,
      "had_ascii_controls": false,
      "had_noncharacters": false,
      "had_private_use": false,
      "had_mirrored_punctuation": false,
      "had_non_ascii_digits": false
    }
  },
  "server": { ... }
}
```

### Example 3: Trojan Source Defense

**Request:**
```bash
curl -X POST http://localhost:8000/api/clean.php \
  -H 'Content-Type: application/json' \
  -d '{
    "text": "access_level = \"user\u202e \u2066// Check if admin\u2069 \u2066if (isAdmin)\u2069 \u2066 {",
    "mode": "safe"
  }'
```

**Note**: Input contains BiDi override characters (RLO, LRI, PDI).

**Response:**
```json
{
  "text": "access_level = \"user // Check if admin if (isAdmin) {\n",
  "stats": {
    "original_length": 74,
    "final_length": 56,
    "characters_removed": 18,
    "mode": "safe",
    "invisibles_removed": 4,
    "homoglyphs_normalized": 0,
    "digits_normalized": 0,
    "advisories": {
      "had_bidi_controls": true,
      "had_mixed_scripts": false,
      "had_default_ignorables": false,
      "had_tag_chars": false,
      "had_orphan_combining": false,
      "confusable_suspected": false,
      "had_html_entities": false,
      "had_ascii_controls": false,
      "had_noncharacters": false,
      "had_private_use": false,
      "had_mirrored_punctuation": false,
      "had_non_ascii_digits": false
    }
  },
  "server": { ... }
}
```

### Example 4: Emoji Preservation

**Request:**
```bash
curl -X POST http://localhost:8000/api/clean.php \
  -H 'Content-Type: application/json' \
  -d '{
    "text": "Hello 👋🏽 world 🌍",
    "mode": "safe"
  }'
```

**Response:**
```json
{
  "text": "Hello 👋🏽 world 🌍\n",
  "stats": {
    "original_length": 20,
    "final_length": 20,
    "characters_removed": 0,
    "mode": "safe",
    "invisibles_removed": 0,
    "homoglyphs_normalized": 0,
    "digits_normalized": 0,
    "advisories": { ... }
  },
  "server": { ... }
}
```

### Example 5: Strict Mode (Maximum Security)

**Request:**
```bash
curl -X POST http://localhost:8000/api/clean.php \
  -H 'Content-Type: application/json' \
  -d '{
    "text": "Café №123 — Price: ৳৫০০",
    "mode": "strict"
  }'
```

**Response:**
```json
{
  "text": "cafe no123 - price: 500\n",
  "stats": {
    "original_length": 24,
    "final_length": 24,
    "characters_removed": 0,
    "mode": "strict",
    "invisibles_removed": 0,
    "homoglyphs_normalized": 1,
    "digits_normalized": 3,
    "advisories": {
      "had_bidi_controls": false,
      "had_mixed_scripts": true,
      "had_default_ignorables": false,
      "had_tag_chars": false,
      "had_orphan_combining": false,
      "confusable_suspected": true,
      "had_html_entities": false,
      "had_ascii_controls": false,
      "had_noncharacters": false,
      "had_private_use": false,
      "had_mirrored_punctuation": false,
      "had_non_ascii_digits": true
    }
  },
  "server": { ... }
}
```

**Note**: Strict mode applied NFKC normalization + casefolding:
- `Café` → `cafe` (é → e, lowercase)
- `№` → `no` (Numero sign)
- `—` → `-` (em dash to hyphen)
- `৳৫০০` → `500` (Bengali Taka sign removed, digits normalized)

## Client Libraries

### JavaScript (Browser)

```javascript
async function sanitizeText(text, mode = 'safe') {
  const response = await fetch('http://localhost:8000/api/clean.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ text, mode })
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.error || 'API request failed');
  }

  return await response.json();
}

// Usage
sanitizeText('Hello\u200Bworld', 'safe')
  .then(result => console.log(result.text))
  .catch(err => console.error(err));
```

### JavaScript (Node.js)

```javascript
const https = require('https');

function sanitizeText(text, mode = 'safe') {
  return new Promise((resolve, reject) => {
    const data = JSON.stringify({ text, mode });

    const options = {
      hostname: 'localhost',
      port: 8000,
      path: '/api/clean.php',
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(data)
      }
    };

    const req = https.request(options, (res) => {
      let body = '';
      res.on('data', (chunk) => body += chunk);
      res.on('end', () => {
        if (res.statusCode === 200) {
          resolve(JSON.parse(body));
        } else {
          reject(new Error(JSON.parse(body).error));
        }
      });
    });

    req.on('error', reject);
    req.write(data);
    req.end();
  });
}

// Usage
sanitizeText('Hello\u200Bworld', 'aggressive')
  .then(result => console.log(result.text))
  .catch(err => console.error(err));
```

### Python

```python
import requests
import json

def sanitize_text(text, mode='safe'):
    url = 'http://localhost:8000/api/clean.php'
    headers = {'Content-Type': 'application/json'}
    payload = {'text': text, 'mode': mode}

    response = requests.post(url, headers=headers, data=json.dumps(payload))

    if response.status_code == 200:
        return response.json()
    else:
        raise Exception(response.json().get('error', 'API request failed'))

# Usage
result = sanitize_text('Hello\u200Bworld', 'safe')
print(result['text'])
```

### PHP

```php
<?php
function sanitizeText($text, $mode = 'safe') {
    $url = 'http://localhost:8000/api/clean.php';
    $data = json_encode(['text' => $text, 'mode' => $mode]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        return json_decode($response, true);
    } else {
        throw new Exception(json_decode($response)->error ?? 'API request failed');
    }
}

// Usage
$result = sanitizeText("Hello\u{200B}world", 'safe');
echo $result['text'];
?>
```

### Ruby

```ruby
require 'net/http'
require 'json'
require 'uri'

def sanitize_text(text, mode = 'safe')
  uri = URI.parse('http://localhost:8000/api/clean.php')
  header = {'Content-Type': 'application/json'}
  payload = {text: text, mode: mode}.to_json

  http = Net::HTTP.new(uri.host, uri.port)
  request = Net::HTTP::Post.new(uri.request_uri, header)
  request.body = payload

  response = http.request(request)

  if response.code == '200'
    JSON.parse(response.body)
  else
    raise JSON.parse(response.body)['error'] || 'API request failed'
  end
end

# Usage
result = sanitize_text("Hello\u200Bworld", 'safe')
puts result['text']
```

### Go

```go
package main

import (
    "bytes"
    "encoding/json"
    "fmt"
    "io/ioutil"
    "net/http"
)

type Request struct {
    Text string `json:"text"`
    Mode string `json:"mode"`
}

type Response struct {
    Text   string                 `json:"text"`
    Stats  map[string]interface{} `json:"stats"`
    Server map[string]interface{} `json:"server"`
}

func sanitizeText(text, mode string) (*Response, error) {
    url := "http://localhost:8000/api/clean.php"
    payload := Request{Text: text, Mode: mode}
    jsonData, _ := json.Marshal(payload)

    resp, err := http.Post(url, "application/json", bytes.NewBuffer(jsonData))
    if err != nil {
        return nil, err
    }
    defer resp.Body.Close()

    body, _ := ioutil.ReadAll(resp.Body)

    if resp.StatusCode == 200 {
        var result Response
        json.Unmarshal(body, &result)
        return &result, nil
    } else {
        var errResp map[string]string
        json.Unmarshal(body, &errResp)
        return nil, fmt.Errorf(errResp["error"])
    }
}

func main() {
    result, err := sanitizeText("Hello\u200Bworld", "safe")
    if err != nil {
        panic(err)
    }
    fmt.Println(result.Text)
}
```

## CORS Configuration

The API is configured to allow cross-origin requests. The default `.htaccess` configuration sets:

```apache
Header set Access-Control-Allow-Origin "*"
Header set Access-Control-Allow-Methods "POST, OPTIONS"
Header set Access-Control-Allow-Headers "Content-Type"
```

### Production CORS Settings

For production, restrict to your domain:

```apache
Header set Access-Control-Allow-Origin "https://yourdomain.example"
```

Or use PHP dynamic CORS in `clean.php`:

```php
$allowedOrigins = ['https://yourdomain.example', 'https://app.yourdomain.example'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
}
```

## Webhook Integration

### Zapier Example

Create a Zapier webhook that calls the API:

1. Trigger: New form submission
2. Action: Webhooks by Zapier → POST
3. URL: `https://yourdomain.example/api/clean.php`
4. Payload Type: JSON
5. Data:
   ```json
   {
     "text": "{{form_text_field}}",
     "mode": "aggressive"
   }
   ```

### n8n Workflow

```json
{
  "nodes": [
    {
      "name": "HTTP Request",
      "type": "n8n-nodes-base.httpRequest",
      "parameters": {
        "url": "https://yourdomain.example/api/clean.php",
        "method": "POST",
        "jsonParameters": true,
        "options": {},
        "bodyParametersJson": "={\"text\": \"{{ $json.input_text }}\", \"mode\": \"safe\"}"
      }
    }
  ]
}
```

## Best Practices

### 1. Always Specify Mode

```javascript
// Good
sanitizeText('Hello world', 'aggressive')

// Bad (relies on default)
sanitizeText('Hello world')
```

### 2. Handle Errors Gracefully

```javascript
try {
  const result = await sanitizeText(userInput, 'safe');
  displayResult(result.text);
} catch (error) {
  console.error('Sanitization failed:', error);
  displayError('Unable to sanitize text. Please try again.');
}
```

### 3. Validate Input Size Client-Side

```javascript
const MAX_SIZE = 1024 * 1024; // 1 MB

if (new Blob([text]).size > MAX_SIZE) {
  alert('Text is too large. Maximum size is 1 MB.');
  return;
}
```

### 4. Use Appropriate Timeout

```javascript
const controller = new AbortController();
const timeout = setTimeout(() => controller.abort(), 5000);

fetch(url, { signal: controller.signal, ... })
  .finally(() => clearTimeout(timeout));
```

### 5. Cache Results (if applicable)

```javascript
const cache = new Map();

async function cachedSanitize(text, mode) {
  const key = `${text}:${mode}`;
  if (cache.has(key)) {
    return cache.get(key);
  }
  const result = await sanitizeText(text, mode);
  cache.set(key, result);
  return result;
}
```

## Troubleshooting

### Issue: "CORS blocked"

**Solution**: Update `.htaccess` to allow your origin, or use same-origin requests.

### Issue: "413 Payload Too Large"

**Solution**: Split text into chunks or compress content before sending.

### Issue: "503 Service Unavailable"

**Solution**: Enable `mbstring` and `intl` PHP extensions via cPanel or php.ini.

### Issue: Slow response times

**Solutions**:
- Enable PHP OpCache
- Increase PHP memory limit
- Use CDN for static assets
- Implement caching proxy (Varnish, nginx)

### Issue: Inconsistent results

**Solution**: Ensure consistent Unicode version across environments. Check `server.unicode_version` in response.

## Support

For API support, bug reports, or feature requests:

- GitHub Issues: https://github.com/johnzfitch/definitelynot.ai/issues
- Documentation: https://github.com/johnzfitch/definitelynot.ai

---

**Last Updated**: 2025-11-08
**API Version**: 2.2.1
**Unicode Version**: 15.0
