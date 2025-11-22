# API Reference

This reference covers the public REST interface exposed at `/api/clean.php`, expected request/response contracts, advisory semantics, and integration recipes for both manual and automated consumers.

## Endpoint Summary

| Endpoint | Method | Description |
| --- | --- | --- |
| `/api/clean.php` | `POST` | Sanitize a text payload and return normalized output plus advisory metadata. |

All responses are JSON encoded with UTF-8 output. Requests must include the `Content-Type: application/json` header unless using form data.

## Request Body

```json
{
  "text": "Paste your raw text here",
  "mode": "safe"
}
```

- `text` *(string, required)* – The raw content to sanitize. Limit 1 MB (1048576 bytes).
- `mode` *(string, required)* – One of `safe`, `aggressive`, or `strict`.

When `mode` is omitted, the API returns HTTP `400` with `code: "invalid_payload"`.

### Query Parameters & Headers

- `Accept: application/json` – Optional but recommended to express preference.
- `X-Request-Id` – Optional identifier echoed back in responses to correlate logs.
- `Authorization` – Not required; deployments should front the API with their preferred auth if exposed publicly.

## Success Response

```json
{
  "text": "Sanitized output...",
  "stats": {
    "original_length": 33,
    "final_length": 31,
    "characters_removed": 2,
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

### Advisory Fields

| Field | Trigger | Operational Guidance |
| --- | --- | --- |
| `had_bidi_controls` | Bidirectional overrides or isolates removed. | Review code blocks or identifiers for Trojan Source risk. |
| `had_mixed_scripts` | Multiple writing systems detected. | Confirm identifiers aren't spoofing ASCII. |
| `had_default_ignorables` | Zero-width / watermark characters stripped. | Safe to accept sanitized output; consider rejecting original. |
| `had_tag_chars` | Deprecated tag characters removed. | Investigate for steganography attempts. |
| `had_orphan_combining` | Combining marks missing a base code point. | Indicates Zalgo text or glyph stacking attack. |
| `confusable_suspected` | ICU Spoofchecker flagged look-alike characters. | Require manual approval before merging. |
| `had_html_entities` | HTML entities decoded during preprocessing. | Inspect the source if the payload should be plain text. |
| `had_ascii_controls` | ASCII control characters stripped (except tab/LF). | Usually indicates log or script injection attempts. |
| `had_noncharacters` | Unicode noncharacters removed. | Reject original payload in regulated environments. |
| `had_private_use` | Private-use characters removed (strict mode). | Scrutinize for watermarking/steganography. |
| `had_mirrored_punctuation` | Mirrored punctuation anomalies detected. | Check for mismatched braces or direction flips. |
| `had_non_ascii_digits` | Non-Latin digits normalized. | Ensure account numbers or IDs remain accurate. |

### Server Metadata

Expose sanitizer build information to help consumers validate compatibility across environments. Extend the object if you add new features (e.g., rate-limit hints).

## Error Responses

| HTTP Status | `error.code` | Cause | Recommended Action |
| --- | --- | --- | --- |
| `400` | `invalid_payload` | Missing `text` or `mode`, malformed JSON. | Fix client request serialization. |
| `400` | `unsupported_mode` | Mode other than `safe`, `aggressive`, `strict`. | Update client to supported modes. |
| `413` | `request_too_large` | Payload exceeds 1 MB. | Chunk the request or trim content. |
| `415` | `unsupported_media_type` | Content-Type not JSON/form. | Set `Content-Type: application/json`. |
| `500` | `server_error` | Unexpected exception, extension missing, or pipeline failure. | Check server logs; respond with fallback sanitization. |

Error responses include:

```json
{
  "error": {
    "code": "invalid_payload",
    "message": "Provide a JSON object with 'text' and 'mode'."
  }
}
```

## Usage Examples

### Curl

```bash
curl -X POST https://example.com/api/clean.php \
  -H 'Content-Type: application/json' \
  -d '{"text":"Hello\u202Eevil","mode":"safe"}'
```

### JavaScript (browser)

```js
async function sanitize(text, mode = 'safe') {
  const response = await fetch('/api/clean.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ text, mode })
  });
  if (!response.ok) {
    const error = await response.json();
    throw new Error(`${error.error.code}: ${error.error.message}`);
  }
  return response.json();
}
```

### PHP (server-to-server)

```php
function sanitize_text(string $text, string $mode = 'safe'): array {
    $ch = curl_init('https://example.com/api/clean.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['text' => $text, 'mode' => $mode], JSON_THROW_ON_ERROR),
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) {
        throw new RuntimeException('Transport error: ' . curl_error($ch));
    }
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
    if ($status >= 400) {
        throw new RuntimeException($decoded['error']['message'] ?? 'Sanitizer failure');
    }
    return $decoded;
}
```

## Integration Checklist

- ✅ Validate response schema in automated tests (JSON schema or contract tests).
- ✅ Log advisory flags for observability and alerting.
- ✅ Enforce HTTPS between clients and the API.
- ✅ Rate-limit API clients to protect server resources.
- ✅ Provide fallbacks if `server_error` occurs (e.g., quarantine message).

## Change Management

When you add a new advisory, field, or mode:

1. Update this reference with the new contract.
2. Increment API version metadata if you expose breaking changes.
3. Notify integrators via release notes or changelog updates.
4. Add regression tests under `tests/` to cover the new behavior.

Accurate documentation keeps consumers aligned with the sanitizer's evolving capabilities.
