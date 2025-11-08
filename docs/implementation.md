# Implementation Notes

This guide drills into the PHP, JavaScript, and asset structure that powers Cosmic Text Linter. Use it when implementing new sanitization passes, updating the UI, or extending tests.

## PHP Sanitization Engine (`api/TextLinter.php`)

### Class Responsibilities

- **`TextLinter::clean`** – Entry point. Accepts raw text and a mode string, orchestrates the pipeline, and returns an associative array consumed by `clean.php`.
- **Normalization helpers** – `nfcNormalize`, `nfkcCasefold`, and related methods encapsulate `Normalizer` usage so modes can toggle canonical versus compatibility normalization.
- **Detector methods** – `spoofAudit`, `detectMirroredPunctuation`, `stripBidiControls`, etc. Each method is responsible for updating advisory flags alongside modifying the text.
- **Utility functions** – Methods such as `asciiDigits` and `normalizePunctuation` convert confusable characters to safe ASCII equivalents in aggressive/strict modes.

Keep the engine stateless—do not rely on globals or class properties beyond the `advisories`/`stats` arrays passed between methods. This ensures thread safety under FPM.

### Adding a New Pass

1. Implement a dedicated private method that receives the working string and returns the transformed value.
2. Decide which modes should run the pass and gate execution accordingly.
3. Update the `$advisories` or `$stats` structures when the pass removes, replaces, or flags content.
4. Register the new method in the ordered pipeline within `clean`.
5. Document the change in `docs/api.md`, `docs/architecture.md`, and relevant UI labels.
6. Add regression coverage (unit test or fixture) in `tests/`.

### Error Handling

- Throw `RuntimeException` with descriptive messages when required PHP extensions are missing.
- Return sanitized output even if optional integrations (e.g., ICU Spoofchecker) are unavailable—flag the limitation via `server.extensions`.
- Ensure all string operations use multibyte-safe functions (`mb_*`).

## API Gateway (`api/clean.php`)

- Validates HTTP method, content type, payload size, and mode.
- Uses `json_decode` with `JSON_THROW_ON_ERROR` to surface malformed bodies.
- Catches exceptions from `TextLinter::clean` and translates them into HTTP `500` with structured error responses.
- Applies CORS and caching headers. Adjust `Access-Control-Allow-Origin` before production deployment.

When modifying headers, keep in mind that the single-page UI fetches from the same origin by default. Cross-origin deployments must maintain compatible CORS policies.

## Browser UI (`index.html`, `assets/js/script.js`, `assets/css/styles.css`)

### HTML Skeleton

- Retro cosmic theme with grid layout.
- Input and output textareas, diff viewer container, advisory badge list, and metadata footer.
- Use ARIA labels to expose control state to screen readers.

### JavaScript Flow

1. Bind submit and keyboard shortcut handlers to the sanitize form.
2. Call `fetch('/api/clean.php')` with JSON body, showing a loading indicator and disabling inputs.
3. On success, update sanitized textarea, counters, and advisory badges.
4. Run client-side diff by importing the LCS helper to display removed characters.
5. Handle errors by surfacing toast notifications and logging diagnostic details.

Avoid insecure operations such as injecting sanitized text via `innerHTML`. Always assign to `.textContent` to preserve escaping.

### CSS Highlights

- Neon gradient backgrounds and starfield animation provide the retro aesthetic.
- Responsive layout uses CSS Grid and flexbox to support desktop and tablet breakpoints.
- Custom properties (`--color-*`) centralize theme colors for quick reskinning.

## Testing Strategy

- **Smoke Script** – `tests/smoke-test.sh` hits the deployed API using sample payloads to validate major advisories.
- **Fixtures** – `tests/test-samples.txt` contains curated examples for manual regression.
- **Suggested Additions** – Add PHPUnit tests for new sanitization passes and Playwright/Cypress suites for UI regression if the project grows.

## Deployment Notes

- PHP 7.4+ with `intl` and `mbstring` enabled is mandatory. Enable ICU Spoofchecker where available for maximal advisory coverage.
- Default `.htaccess` enforces security headers; adjust CORS for production origins and consider a strict Content Security Policy.
- For Nginx, mimic the rewrite rules shown in `README.md` to route `/api/` traffic to `clean.php`.

## Release Process

1. Update documentation (overview, architecture, API, implementation) alongside code changes.
2. Bump semantic version in `README.md` and server metadata.
3. Tag the release and publish changelog notes summarizing advisories or pipeline adjustments.
4. Announce to integrators—highlight new advisories or required client changes.

Maintaining these implementation notes keeps the project approachable and reduces onboarding time for new maintainers.
