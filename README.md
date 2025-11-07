# Cosmic Text Linter v2.2.1

A Unicode-security-aware text sanitizer with a retro 90s space aesthetic. Removes invisible characters, defends against Unicode-based attacks (Trojan Source, homoglyph spoofing), and preserves legitimate multilingual text.

## Features

- **Three Operation Modes**
  - **Safe**: Preserves emoji, RTL marks, and multilingual text while detecting threats
  - **Aggressive**: Latin-only mode, strips format characters and homoglyphs
  - **Strict**: Maximum security with NFKC casefold, PUA removal, and digit normalization
- **16-Step Unicode Security Pipeline** covering HTML entity decoding, BiDi defense, homoglyph normalization, TAG filtering, Spoofchecker audit, mirrored punctuation detection, and more
- **Retro Cosmic UI** with animated starfield, neon glows, responsive grid layout, accessible controls, and keyboard shortcuts
- **Comprehensive Stats & Advisories** to highlight invisible characters, confusables, RTL issues, and more
- **RESTful API** at `/api/clean.php` for programmatic integration

## Requirements

- PHP 7.4+ with extensions:
  - `mbstring`
  - `intl` (includes `IntlChar`, `Spoofchecker` when enabled)
- Apache with `mod_rewrite`, `mod_headers`, `mod_deflate`, and `mod_expires` (optional but recommended)
- Modern browser supporting ES6 and CSS grid (Chrome/Edge 90+, Firefox 88+, Safari 14+)

## Directory Structure

```
cosmic-text-linter/
├── index.html
├── .htaccess
├── README.md
├── assets/
│   ├── css/
│   │   └── styles.css
│   └── js/
│       └── script.js
├── api/
│   ├── clean.php
│   └── TextLinter.php
└── tests/
    ├── test-samples.txt
    └── smoke-test.sh
```

## Installation (cPanel Shared Hosting)

### 1. Verify PHP Configuration
```bash
php -v
php -m | grep -E 'mbstring|intl'
```
If `mbstring` or `intl` are missing, open **Select PHP Version** in cPanel, enable the extensions, and click **Save**.

### 2. Upload Files
1. Download or clone this repository locally.
2. In cPanel File Manager (or via SFTP), upload the entire `cosmic-text-linter` directory to `public_html/` or to your desired subdirectory/subdomain root.
3. Ensure permissions:
   - Directories: `755`
   - PHP files and assets: `644`
   - `.htaccess`: `644`

### 3. Adjust Rewrite Base (if needed)
The included `.htaccess` assumes deployment at `/cosmic-text-linter/`. If you use a different directory (or deploy at the web root), update `RewriteBase` accordingly.

### 4. Test Extensions and PHP Errors
- Visit `https://yourdomain.example/cosmic-text-linter/`
- Check `error_log` in File Manager for PHP warnings or fatal errors

## Apache Configuration (.htaccess)

The supplied `.htaccess` handles:
- API routing (`/api/*` → `api/clean.php`)
- Security headers (X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Referrer-Policy)
- CORS headers (default `*` for testing—lock down in production)
- PHP resource limits (2 MB upload/post, 128 MB memory, 30 s timeout)
- Compression (mod_deflate) and asset caching (mod_expires)
- Denies direct access to `.md`, `.txt`, `.log`

If you deploy at web root, change `RewriteBase /cosmic-text-linter/` to `/`.

## Nginx Reference

```nginx
location /cosmic-text-linter/api/ {
    try_files $uri $uri/ /cosmic-text-linter/api/clean.php?$args;
}

location /cosmic-text-linter/ {
    try_files $uri $uri/ =404;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
}
```

## Usage

### Web Interface
1. Open `index.html` in your browser.
2. Paste or type text into **Raw Input**.
3. Choose operation mode (Safe/Aggressive/Strict).
4. Click **Sanitize Text** or press <kbd>Ctrl</kbd>/<kbd>Cmd</kbd> + <kbd>Enter</kbd>.
5. Review stats and advisories, then copy sanitized text.

### API Endpoint
```
POST /cosmic-text-linter/api/clean.php
Content-Type: application/json
{
  "text": "Hello\u200Bworld",
  "mode": "safe"
}
```
Example curl:
```bash
curl -X POST https://yourdomain.example/cosmic-text-linter/api/clean.php \
  -H 'Content-Type: application/json' \
  -d '{"text":"Hello\u200Bworld","mode":"safe"}'
```
Example response:
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

## Testing

### Automated Smoke Test
1. Edit `tests/smoke-test.sh` to point `API_URL` to your deployed endpoint (or export the variable before running).
2. Make executable and run:
```bash
cd tests
chmod +x smoke-test.sh
API_URL="https://yourdomain.example/cosmic-text-linter/api/clean.php" ./smoke-test.sh
```

### Manual Sample Review
Open `tests/test-samples.txt` and paste each input into the UI to confirm expected results per mode.

## Browser Support Matrix

| Browser | Minimum Version |
| --- | --- |
| Chrome / Edge | 90 |
| Firefox | 88 |
| Safari | 14 |
| iOS Safari | 14 |
| Chrome Android | 90 |

## Security Notes

- Maximum input size enforced at 1 MB (bytes) server-side.
- Spoofchecker integration flags confusables when available.
- TAG block characters stripped while preserving valid emoji flags (Safe/Strict).
- CORS defaults to `*` for testing; set to your production origin in `.htaccess`.
- Avoid modifying `api/clean.php` headers unless you understand CORS implications.
- Consider adding a strict Content Security Policy at the hosting layer to disallow inline scripts and third-party domains beyond Google Fonts.

## Version History

- **v2.2.1** – Production release with ICU enum resolution and enhanced advisories
- **v2.2.0** – Added advisory matrix and Spoofchecker integration
- **v2.1.0** – Expanded Unicode sanitation pipeline
- **v2.0.0** – Initial release with Safe/Aggressive/Strict modes

## Deployment Checklist

- [ ] PHP 7.4+ with `mbstring` and `intl` enabled
- [ ] Uploaded full directory with correct permissions
- [ ] Updated `.htaccess` `RewriteBase` for deployment path
- [ ] Adjusted CORS origin from `*` to production domain
- [ ] Ran smoke tests against live endpoint
- [ ] Verified advisories using sample inputs
- [ ] Confirmed SSL is active on production host

## cPanel Troubleshooting Tips

| Symptom | Possible Cause | Fix |
| --- | --- | --- |
| `500 Internal Server Error` | Missing `intl` or `mbstring` | Enable extensions in **Select PHP Version** |
| API returns `Payload too large` | Upload exceeds 1 MB | Split or compress input |
| API returns HTML instead of JSON | `mod_rewrite` disabled | Ask host to enable or adjust `.htaccess` |
| CORS blocked in browser | Production origin not allowed | Update `Access-Control-Allow-Origin` header |
| Spoofchecker missing | ICU library not built with spoof checker | Optional; advisories fall back gracefully |
| UI starfield stutters | Low-power device | Enable reduced-motion in OS or add custom CSS |

## Credits

Built by Internet Universe (internetuniverse.org). Inspired by Unicode security research and specifications: UAX #9, UTS #39, UAX #31, UTS #51.
