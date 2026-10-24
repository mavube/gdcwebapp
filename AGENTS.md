# AGENTS.md

Symfony 7.4 LTS app for Global Dynamics Consulting ("GDC Web App"). Work is
delivered in phases and each phase's brief is fed verbatim into the user's local
Pinokio engine for generation.

## PHP version: must run on 8.3

This app is deployed on Laragon with **PHP 8.3**, which cannot be upgraded
because other applications on that machine depend on it. The dependency set is
therefore pinned to 8.3-compatible versions, and `composer.json` sets
`config.platform.php = 8.3.16` so a later `composer update` cannot silently
resolve to packages that require 8.4.

Consequences for generated code:

- Do not use PHP 8.4-only syntax or functions. This includes property hooks
  (`public string $x { get => ... }`), asymmetric visibility (`private(set)`),
  `array_find` / `array_find_key` / `array_any` / `array_all`, and the new
  `mb_*` case helpers such as `mb_trim` and `mb_ucfirst`.
- Do not raise the `php` requirement in `composer.json` above `>=8.2`.
- Do not loosen these constraints: `doctrine/doctrine-bundle ^2`, 
  `doctrine/doctrine-migrations-bundle ^3`, `phpunit/phpunit ^12`. Their next
  major versions require PHP 8.4 and will break the Laragon deployment.

If a dependency genuinely needs 8.4, upgrade Laragon first — do not use
`--ignore-platform-reqs`, which hides the mismatch and moves the failure to
runtime.

## Visual design: the Nova port

The public site's visual language comes from the **Nova** reference template
(`nova-astro-v1.0.0.zip`), audited on branch `design/template-drop`. Nova is an
Astro 7 / React 19 / Tailwind **v4** application; this project is Twig and
Tailwind **3.4** with no bundler. Only its *design language* was ported — never
its components, build, or markup.

The source archive is `nova-astro-v1.0.0.zip`, delivered on branch
`design/template-drop`. That branch is a **reference only**: do not merge it and
do not open a PR from it. Fetch it and read the files if you need the original.

Nova's palette, which the tokens encode:

| Nova token | Value | Mapped to |
|---|---|---|
| surface / brand-light | `#fffdf9` | `canvas` |
| surface-dim / brand-cream | `#f4f0e9` | `cream` |
| surface-container | `#eee8df` | `sand` |
| outline-variant | `#ddd4c9` | `linen` |
| outline / brand-accent | `#b9aea2` | `taupe` |
| on-surface / brand-primary / brand-dark | `#161616` | `navy` |
| accent / surface-tint | `#b98b6e` | `regal` |
| on-surface-variant / secondary | `#68635d` | `muted` |
| error | `#b42318` | `danger` |
| success | `#2f6b4f` | `ok` |

Type scale: display `clamp(2.75rem,7vw,4rem)`/0.96/-0.055em, headline
`clamp(2rem,4.5vw,2.625rem)`/0.98/-0.05em, title 1.25rem/1.3, body 1rem/1.65,
label 0.6875rem/+0.18em. Radii: field 0.75rem, card 1.5rem, panel 2rem.
Section rhythm is a single `clamp(3.5rem,7vw,6.5rem)`.

Rules that follow from the port:

- **Tailwind v3 only.** Nova's CSS uses `@theme`, `@import "tailwindcss"` and
  `@plugin`. Tailwind 3.4 cannot compile any of these. Express new design
  values as `theme.extend` in `tailwind.config.js` or `@layer components` in
  `assets/styles/app.css`.
- **Token names `regal`, `navy`, `canvas` are load-bearing.** `assets/funnel.js`
  and the templates reference them; the Nova palette was mapped onto these
  names rather than replacing them. `regal` is now the copper accent, `navy` the
  near-black ink, `canvas` the warm off-white.
- **Use the `ui-*` contract classes** (`ui-container`, `ui-section`,
  `ui-type-display|headline|title|body|label`, `ui-button` +
  `ui-button-primary|accent|outline`, `ui-card`, `ui-card-interactive`,
  `ui-form-input`, `ui-form-label`) instead of repeating long utility strings.
  They encode Nova's rules: one shared card hover, fluid section rhythm, no
  per-card animation.
- **Do not use dots, pulsing elements, or ghost buttons that fade into a light
  background.** Nova's design rules forbid all three.
- **Respect reduced motion.** Any new animation needs a
  `prefers-reduced-motion` escape, as `.ui-card-interactive` has.

### Fonts and images are licence-gated

Nova bundles Satoshi, Outfit, Gambarino, Anton and Barlow Condensed. Its
`ASSETS-LICENSES.md` states the font files must be checked against their
upstream licences before redistribution, and its demo imagery must be replaced
before commercial use. Neither the licences nor the imagery rights are
verifiable from the archive, so **no third-party font or image binary is
committed**. The font stack names the Nova faces first and falls back to the
system stack, so the site renders correctly today and upgrades to the real faces
the moment the files are added to `public/` with a `@font-face` block.

Do not add Google Fonts or any other CDN link. Do not commit an image whose
rights are not documented.

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
