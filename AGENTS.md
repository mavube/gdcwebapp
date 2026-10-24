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

Nova supplied a warm, near-monochrome palette. That palette has since been
**replaced by GDC's own brand kit** (taken from the live site's Elementor global
kit: primary `#0181C9`, secondary `#1A2E38`, accent `#EC7600`), so the values in
`tailwind.config.js` are GDC's, not Nova's. What survives from Nova is the
*structure*: which token names exist, and the type/radii/rhythm scales below.

Semantic tokens, preferred for new work:

| Token | Value | Use |
|---|---|---|
| `brand` | `#0181C9` | primary actions, links |
| `brand-deep` | `#016BA6` | pressed/hover blue |
| `brand-ink` | `#1A2E38` | deep teal-navy headings, dark surfaces |
| `accent` | `#EC7600` | eyebrows, highlights |
| `accent-deep` | `#C96300` | pressed orange |
| `surface` | `#FFFFFF` | page surface |
| `mist` | `#F4F7FA` | light section wash |

Legacy alias tokens are **still load-bearing** — `assets/funnel.js` and the admin
templates (`admin/login.html.twig`, `admin/telemetry.html.twig`, which the Nova
port did not restyle) reference them. They now carry GDC values:

`regal` `#0181C9`, `navy` `#1A2E38`, `canvas` `#FFFFFF`, `ember` `#016BA6`,
`cream` `#F4F7FA`, `sand` `#EEF2F6`, `linen` `#DCE3EA`, `taupe` `#93A2B0`,
`muted` `#5A6B7A`, `danger` `#B42318`, `ok` `#2F6B4F`.

Prefer the semantic names in new markup; keep the aliases so existing templates
and `funnel.js` keep resolving. `slate-800` is aliased to the brand ink so
`border-slate-800/20` in `funnel.js` keeps matching.

Type scale: display `clamp(2.75rem,7vw,4rem)`/0.96/-0.055em, headline
`clamp(2rem,4.5vw,2.625rem)`/0.98/-0.05em, title 1.25rem/1.3, body 1rem/1.65,
label 0.6875rem/+0.18em. Radii: field 0.75rem, card 1.5rem, panel 2rem.
Section rhythm is a single `clamp(3.5rem,7vw,6.5rem)`.

Rules that follow from the port:

- **Tailwind v3 only.** Nova's CSS uses `@theme`, `@import "tailwindcss"` and
  `@plugin`. Tailwind 3.4 cannot compile any of these. Express new design
  values as `theme.extend` in `tailwind.config.js` or `@layer components` in
  `assets/styles/app.css`.
- **Legacy token names `regal`, `navy`, `canvas` are load-bearing.** They are
  referenced by `assets/funnel.js` and by the admin templates the port did not
  restyle, so they were kept as aliases carrying GDC values rather than removed:
  `regal` is now GDC blue, `navy` the deep teal-navy ink, `canvas` white. New
  markup should prefer the semantic names (`brand`, `brand-ink`, `surface`).
- **Use the `ui-*` contract classes** instead of repeating long utility strings.
  The full set is `ui-container`, `ui-section`, `ui-band-ink`, `ui-hero-wash`,
  `ui-type-display|headline|title|body|label`, `ui-button` +
  `ui-button-primary|accent|outline`, `ui-card`, `ui-card-interactive`,
  `ui-form-input`, `ui-form-label`. They encode the shared card hover, the fluid
  section rhythm, and the "no per-card animation" rule.
- **Do not use dots, pulsing elements, or ghost buttons that fade into a light
  background.** Nova's design rules forbid all three.
- **Respect reduced motion.** Any new animation needs a
  `prefers-reduced-motion` escape, as `.ui-card-interactive` has.

### Fonts and images are licence-gated

Nova bundles Satoshi, Outfit, Gambarino, Anton and Barlow Condensed. Its
`ASSETS-LICENSES.md` states the font files must be checked against their
upstream licences before redistribution, and its demo imagery must be replaced
before commercial use. Neither the licences nor the imagery rights are
verifiable from the archive, so **none of Nova's font or image binaries were
committed**, and Nova's own faces are not in the stack.

The fonts actually committed are **Manrope** (body) and **Plus Jakarta Sans**
(headings), both **SIL Open Font License 1.1**, which permits redistribution.
Their `name` tables carry the OFL URL as evidence. The committed files are the
latin and latin-ext woff2 subsets only, self-hosted under `public/fonts/` with
`@font-face` blocks in `assets/styles/app.css`; each family is a variable font
(weight axis 200–800) so one file per subset covers every weight.

To add another face, verify its licence the same way — read the `name` table of
the binary (`licence URL` / `licence` records) and confirm it is OFL or
otherwise redistributable — and record the result in the `@font-face` comment.

Do not add Google Fonts or any other CDN link: the app must make no
third-party request on first paint. Do not commit an image whose rights are not
documented.

## Commands

Run everything from the repository root; `bin/console` does not work from `/tmp`.

```bash
env -u DATABASE_URL php vendor/bin/phpunit   # full suite (test env, var/test.db)
php bin/console lint:twig templates
php bin/console lint:yaml config
php bin/console lint:container
php bin/console doctrine:schema:validate
php bin/console doctrine:migrations:diff --allow-empty-diff   # must report no changes
npm run build                          # Tailwind CSS + copies assets/funnel.js to public/build
```

`env -u DATABASE_URL` matters: see the note in Testing notes about the
environment export overriding `.env.test`.

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

## Catalogue and content blocks

Two entities now drive what the public site shows, so copy and offers are edited
in the database rather than in Twig.

- `Service` — a sellable offer. `slug` is the URL key (`/services/{slug}`),
  `pillar` groups it on the index, and `funnelTrack` decides which deck that
  service opens. `featured` marks the flagship offers.
- `ContentBlock` — one editable section, addressed by `blockKey`
  (`home.hero`, `home.approach`, `services.intro`, …). `active = false` removes
  the section from the page entirely; the template asks
  `blocks['home.approach']|default(null)` and skips a null block.

`ContentBlockRepository::findActiveKeyed()` returns every active block as one
map so a page renders from a single query instead of one lookup per section.

`FunnelConfigurator` builds the deck's configuration for a service (its track,
per-track field list and copy, the slug→track map the JS switches on). It is
exposed to Twig as `gdc_funnel_config`, a **function** rather than a global:
globals are evaluated when the Twig environment boots, which would run a
database query on every request including error pages.

That config is serialised into a `<script type="application/json">` block in
`base.html.twig`, with `JSON_HEX_TAG` set. Without it, admin-supplied copy
containing `</script>` would close the block and inject markup.

## Validation groups on CourseInquiry

`CourseInquiry` serves three entry points and two funnel tracks, so its
constraints are split across groups. Passing the wrong group silently skips
rules.

- `Default` — shared rules (name, email format, phone).
- `track` — the `/training/{slug}` form, where a programme is mandatory.
- `deck` — the slide-out conversion funnel, which does not choose a programme.
- `corporate` — the corporate-domain email rule. **Individual-track
  submissions must not run in this group**, or a professional buying their own
  PMP seat with a Gmail address would be rejected.
- `organisation` — the corporate-only fields (`companyName`,
  `organizationScope`), which an individual enrolment does not ask for.

Do not build the group list by hand. `CourseInquiry::validationGroups()` derives
it from the resolved `funnelTrack` (`FunnelTrack::requiresCorporateEmail()` /
`asksForOrganisation()`), and always includes `Default`. The controller calls
that method for both the form and the deck.

The rule that matters: **always include `Default` alongside the specific group.**
Passing a group replaces the default rather than adding to it, so validating
with `["deck"]` alone skips every ungrouped constraint.

When no track has been resolved, `validationGroups()` falls back to the strict
(organisation) rules rather than the permissive ones — an unknown tier must not
silently buy the individual-track exemptions.

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

- The first run on a fresh clone needs the test schema, because `var/test.db` is
  git-ignored and PHPUnit does not create it. `doctrine:database:create` does
  **not** work for SQLite ("Operation ... is not supported by platform"), and
  the driver creates the *file* on connect but not the tables. Migrate instead:

  ```bash
  env -u DATABASE_URL APP_ENV=test php bin/console doctrine:migrations:migrate --no-interaction
  ```

  Without this the suite fails with `no such table: gdc_admin_users`.
- Migrating the test env can itself fail on a *stale* `var/test.db` — one that
  already has the tables but no `doctrine_migration_versions` rows (typically
  left over from an earlier phase whose schema was created outside the migration
  system). It then tries to re-apply the first migration and dies with
  `The table with name "gdc_training_programs" already exists.` A test database
  holds nothing worth keeping, so delete the file and migrate fresh:

  ```bash
  rm var/test.db                                   # PowerShell: Remove-Item var\test.db
  env -u DATABASE_URL APP_ENV=test php bin/console doctrine:migrations:migrate --no-interaction
  ```

  If the file must be kept, stamp its existing schema as applied instead:
  `php bin/console doctrine:migrations:version --add --all --no-interaction --env=test`.
- The rate limiter is backed by `cache.rate_limiter`, which persists across
  tests. Clear that pool between tests or the sixth submission in a suite gets a
  `429`.
- `var/test.db` persists between runs and tests are **not** wrapped in a
  transaction, so a mutation made by one test is visible to the next. A test that
  edits a row must normalise it *before* asserting, not just restore it
  afterwards, or a failure in one run leaves the row wrong and the next run fails
  for an unrelated reason. Use `try/finally` so an assertion failure still
  restores state. `testInactiveContentBlockDoesNotRender` is the reference.
- The logout route is `/logout`, not `/admin/logout`.
- Run PHPUnit with `DATABASE_URL` unset. The `.env.test` value only wins if the
  environment does not already export `DATABASE_URL`; a stale export pointing at
  `var/data.db` makes the suite run against — and mutate — the development
  database. To check which file the test environment actually opens:

  ```bash
  php bin/console doctrine:query:sql "SELECT file FROM pragma_database_list" --env=test
  ```

  It must print `var/test.db`. `debug:config doctrine dbal` does **not** reveal
  this — it prints the unresolved `%env(resolve:DATABASE_URL)%` placeholder.
