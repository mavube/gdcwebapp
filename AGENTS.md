# AGENTS.md

Symfony 7.4 LTS app for Global Dynamics Consulting ("GDC Web App"). Work is
delivered in phases and each phase's brief is fed verbatim into the user's local
Pinokio engine for generation.

## Commands

Run everything from the repository root; `bin/console` does not work from `/tmp`.

```bash
php vendor/bin/phpunit                 # full suite (test env, var/test.db)
php bin/console lint:twig templates
php bin/console lint:yaml config
php bin/console lint:container
php bin/console doctrine:schema:validate
php bin/console doctrine:migrations:diff --allow-empty-diff   # must report no changes
npm run build                          # Tailwind CSS + copies assets/funnel.js to public/build
```

The dev server used for manual checks is `php -S 127.0.0.1:8123 -t public public/index.php`.

## Database

SQLite by default (`var/data.db`, `var/test.db`), although `.env` names
PostgreSQL. Because the project must stay portable, migrations are written with
the DBAL Schema API rather than raw SQL, and they must not hardcode a platform.
`doctrine:migrations:diff` reporting "no changes detected" is the acceptance
signal that the migration and the entity mapping agree.

Fixtures seed one administrator: `admin@gdcconsulting.com` / `ChangeMe!2026`
(overridable via `GDC_ADMIN_EMAIL` / `GDC_ADMIN_PASSWORD`). Tests create the
admin themselves, so `doctrine:fixtures:load` is not a prerequisite for the
suite.

## Validation groups on CourseInquiry

`CourseInquiry` serves two different entry points, so its constraints are split
across groups. Passing the wrong group silently skips rules.

- `Default` — shared rules (name, email format, corporate-domain rule, phone).
- `track` — the `/training/{slug}` form, where a programme is mandatory.
- `deck` — the slide-out conversion funnel, which does not choose a programme but
  does collect `organizationScope`.

Always include `Default` alongside the specific group. Validating with
`["deck"]` alone skips `Default`, which silently disables the corporate-email
rule — a consumer mailbox then gets a `201`.

## Funnel and telemetry invariants

- Both `/funnel/*` endpoints are JSON-only. A validation failure must return
  `422` with an `errors` map, never a redirect or HTML; the deck must not cause a
  navigation event.
- `InquirySanitizer` is the only path to persistence for user text. It sanitizes,
  strips tags, decodes entities, then strips tags again. That final strip is
  deliberate: decoding can turn `&lt;script&gt;` back into a live tag. Do not
  reorder those steps.
- The `gdc.text` sanitizer must keep `allow_safe_elements: true`. With it off,
  the sanitizer's default action is `drop`, which deletes an element *and its
  text*, so `<b>Acme</b> Ltd` silently becomes ` Ltd`.
- The telemetry dashboard escapes stored values; never render them unescaped.

## Testing notes

- The rate limiter is backed by `cache.rate_limiter`, which persists across
  tests. Clear that pool between tests or the sixth submission in a suite gets a
  `429`.
- `var/test.db` persists between runs, so tests that assert on "the saved lead"
  must delete existing rows first.
- The logout route is `/logout`, not `/admin/logout`.
