# TOFU (Template-Oriented Form Utilities)

WordPress plugin (PHP 8.1+, GPLv3+) for building multi-step forms using PHP templates.
Handles validation, session storage, file uploads, email notifications, DB recording, and bot
protection — all configured in code (no WP admin settings UI; there is an admin *viewer* for
recorded submissions, see below).

**Namespace:** `TofuPlugin\` (PSR-4, mapped to `src/`)
**Entry point:** `template-oriented-form-utilities.php`
**Form flow:** Input Page → *(optional)* Confirm Page → Result Page

For the public API (registering a form, writing Input/Confirm/Result templates, AJAX/headless
clients) **do not re-derive it here — read `docs/index.md` and the pages it links to.** This file
only covers architecture, conventions, and dev workflow.

---

## Architecture

```
functions.php (FormConfig registration, on `init`)
    └── TofuPlugin\Helpers\Form::register(FormConfig)
            └── TofuPlugin\Models\Form (state machine: actions, session, mail, DB record)
                    ├── actionInput()   — validate + store session, redirect to confirm/result
                    ├── actionConfirm() — verify + send emails + optional DB save, redirect to result
                    └── redirect()      — to input | confirm | result
```

Custom endpoint: `?_tofu_key=<base64-JSON>` (registered via `add_rewrite_endpoint`), handled on
`template_redirect` by `TofuPlugin\Init\Endpoint`. An opt-in REST API (`tofu/v1`) exists for
AJAX/headless forms via `TofuPlugin\Init\RestEndpoint` — see `docs/ajax/`.

### `src/` layout

| Dir | Responsibility |
|---|---|
| `Init/` | WordPress integration: `Initializer` (activation/deactivation/upgrade), `Migrate` (runs `migrations/`), `Endpoint` (`_tofu_key` handling), `RestEndpoint` (opt-in `tofu/v1` REST routes + CORS), `AdminPage` (admin UI listing recorded submissions) |
| `Helpers/` | Static-façade public API: `Form` (the class themes call — register/render/verify/redirect), `Session`, `Uploader`, `Encryptor` (AES via `AUTH_KEY`/`SECURE_AUTH_KEY`), `Template` (`get_template_part` + `{field}` placeholders), `Sanitizer`, `ReCAPTCHA`, `Turnstile`, `Directory` |
| `Models/` | Stateful domain objects: `Form` (core action flow), `Validation`, `Mail`, `Session`/`Record` (DB models extending `Base\DatabaseModels`), `FieldValueCollection`, `UploadedFileCollection`, `ValidationErrorCollection`, `Optional` |
| `Structure/` | Immutable config/value objects, PHP 8.1 promoted `readonly` properties + named args: `FormConfig`, `TemplateConfig`, `MailConfig`, `MailRecipientsConfig`/`Collection`, `MailAddress`, `ValidationConfig`, `ValidationError`, `ReCAPTCHAConfig`, `TurnstileConfig`, `FieldValue`, `UploadedFile`, `DatabaseModelColumn` |
| `Base/` | `DatabaseModels` (abstract active-record base), `Migration` (abstract; `sql()` / `useRawQuery()`) |
| `Validation/` | The in-house validation engine (no external dependency). `Validator` (run loop + empty/implicit semantics), `Rule` (abstract base — see its docblock for the constraints subclasses rely on), `RuleRegistry` (clone-per-attribute prototypes), `RuleParser` (pipe-string and array forms), `MessageResolver`, `GettextTranslator`, `Messages` (every message string, as literal `__()` calls for `wp i18n make-pot`), `Support/{Value,UploadedFileInspector}` |
| `Validation/Rules/` | Every rule class, including TOFU's own `RequiredFileRule` (`required_file`, plus its legacy alias `custom_required_file`), `MaxMbRule` (`max_mb`) and `MimeTypeRule` (`mime_type`). Several labels deliberately share one class (`regex`/`matches`, `integer`/`number`, `default`/`defaults`) — **labels are never removed**, since an unknown rule name throws at parse time and would white-screen a live form. `required`, the `required_*`/`prohibited_*` families and `required_file` all share `Support\Presence` so they agree about what counts as an answer, files included |
| `Shortcodes/` | **Empty** — no shortcodes are provided by this plugin |
| `Consts.php` | Plugin-wide constants (query/cookie/nonce keys, `SESSION_EXPIRY`, `REST_NAMESPACE=tofu/v1`, upload/log subfolders) |
| `Logger.php` | Monolog wrapper, active only when `WP_DEBUG === true`, logs to `wp-content/uploads/tofu-logs/` |

### DB tables (created on activation via `migrations/`)

| Table | Purpose |
|---|---|
| `wp_tofu_migrate` | Tracks executed migrations |
| `wp_tofu_sessions` | Encrypted session storage; unique on `(form_id, session_key)`; indexed on `expiration` |
| `wp_tofu_records` | Encrypted (AES-256-CBC) form submission records — opt-in via `FormConfig::$saveToDatabase`, field selection via `ValidationConfig::$records`. Saved after all emails dispatch; a save failure is logged, non-fatal. Viewable in wp-admin via `Init/AdminPage.php` |

---

## Development

```bash
composer install    # install dependencies
composer phpstan     # PHPStan level 5 static analysis (src/, bootstrap: tests/bootstrap-phpstan.php)
composer test        # PHPUnit (tests/Unit, bootstrap: tests/bootstrap.php)
composer check        # phpstan + test (NOT test:scoped — see note below)
composer build         # PHP Scoper production build → build/ (vendor namespaced under TofuVendor\)
./install-tools.sh      # fetches tools/php-scoper.phar (gitignored), required before `composer build`
```

**Known issue — do not "fix" without discussing first:** `phpunit.scoped.xml` is currently
byte-identical to `phpunit.xml` (`bootstrap="tests/bootstrap.php"`), so `composer test:scoped`
does not actually exercise the scoped build in `build/` despite `tests/bootstrap-scoped.php`
existing for that purpose. There is no PHP CI (`.github/workflows/` only deploys the Astro docs
site in `page/`) — `composer check` must be run locally before pushing.

## Coding conventions

- PSR-12-ish style, 4-space indent, `namespace` + `use` at top, docblocks on public methods
- `Structure/` value objects use PHP 8.1 constructor property promotion + named arguments — follow
  this pattern for new config objects rather than plain properties + setters
- No phpcs/ESLint/Prettier are configured — PHPStan (level 5, `phpstan.neon`) is the only enforced
  static check; match the surrounding file's style since nothing else will catch drift
- No JS build step; `assets/js/*.js` are hand-written, enqueued directly by `Form::embedScript()`

## Security conventions

- Nonce verification is automatic via `Form::formClose()` — never bypass it
- Use `ValidationConfig::$allows` as a field whitelist to prevent mass-assignment
- Never output raw `Form::value()` inside HTML attributes — use the default (`$raw = false`)
- Guard confirm/result pages with `Form::verifySession()` / `Form::verifySubmit()`
- Secrets (reCAPTCHA/Turnstile keys) belong in `wp-config.php` or env vars, never hardcoded
- REST routes are opt-in per form (`ajaxEnabled: true`); `corsOrigins` requires exact origins —
  wildcard `*` is not supported

## Repo conventions

- Use the `create-issue` skill to log bugs/features/tasks as `issues/YYYY-mm-dd-hh-ii-ss.md`
- The `wp-phpstan`, `wp-plugin-development`, and `wp-plugin-directory-guidelines` skills under
  `.claude/skills/` are vendored from `WordPress/agent-skills` and pinned in `skills-lock.json`
  (repo root). If that lockfile's tooling is ever re-run, it may recreate `.github/skills/` —
  re-run `git mv .github/skills .claude/skills` and delete the stray `.github/skills/` if so.
- Public API docs live in `docs/` (settings reference, page templates, AJAX/headless guides) —
  update them alongside any change to `Structure/` or `Helpers/Form.php` public methods
