# GDC Web App

Corporate front-end for Global Dynamics Consulting. Symfony 7.4 LTS, Doctrine ORM 3, Twig, Tailwind CSS compiled locally.

## Requirements

- PHP 8.3 (the deployment target; see `AGENTS.md` for why this is pinned)
- Composer 2
- Node 18+ (only to build CSS and copy the JS)

## Setup

```bash
composer install
npm install && npm run build
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction
php -S 127.0.0.1:8000 -t public public/index.php
```

Then open http://127.0.0.1:8000

`npm run build` compiles the CSS *and* copies `assets/site.js` and
`assets/funnel.js` into `public/build/`. `npm run build:css` alone leaves those
two files missing, and the navigation toggle and enquiry form then do nothing.

Build the schema with `doctrine:migrations:migrate`, not
`doctrine:schema:create`. The latter writes no `doctrine_migration_versions`
rows, so the database is afterwards invisible to the migration system: the next
phase's migration is skipped and the app fails with a "no such column" error
that looks like a code bug. If you have already created a database that way,
delete `var/data.db` and re-run the migrate step.

On Pinokio, run `start.json` instead: it performs the setup steps above and opens the browser.

## Database

Defaults to SQLite at `var/data.db`. Override with `DATABASE_URL`:

```
DATABASE_URL="mysql://user:pass@127.0.0.1:3306/gdc"
DATABASE_URL="postgresql://user:pass@127.0.0.1:5432/gdc"
```

## Routes

| Method | Path | Name |
|---|---|---|
| GET | `/` | `app_home` |
| GET | `/services` | `app_service_index` |
| GET | `/services/{slug}` | `app_service_show` |
| GET | `/training/{slug}` | `app_training_show` |
| POST | `/training/{slug}/inquiry` | `app_inquiry_submit` |
| GET, POST | `/login` | `app_admin_login` |
| GET | `/logout` | `app_admin_logout` |
| GET | `/admin/telemetry` | `app_admin_telemetry` |
| GET, POST | `/admin/content` | `app_admin_content_index` |

`{slug}` is constrained to `[a-z0-9-]+` on both training and services, so
malformed input never reaches the database.

The `/admin/*` routes are behind the firewall and require an operator login.

## Tests

```bash
composer test
```

That deletes `var/test.db`, rebuilds it from the migrations, and runs PHPUnit,
which is the only sequence that is correct after a migration is added. To run
PHPUnit on its own, having already migrated the test database:

```bash
env -u DATABASE_URL php vendor/bin/phpunit
```

`env -u DATABASE_URL` is required: an exported `DATABASE_URL` overrides
`.env.test`, and the suite then runs against — and mutates — the development
database.

The suite covers rendering, the CMS-driven layouts, the bento matrix, XSS
escaping, SQL injection attempts, 404 handling, form persistence, validation
failures, rate limiting, and the admin content editor.

## Architecture notes

**Escaping.** Twig autoescaping is on for `.html.twig`. No `|raw` is applied to any database-sourced value. `program.description` is rendered with `whitespace-pre-line` rather than raw HTML, so newlines survive without opening an injection path.

**Validation.** `CourseInquiry::$trainingProgram` is `NOT NULL` at the database level and carries `Assert\NotNull`. Without that constraint a tampered submission reached the database and threw `NotNullConstraintViolationException` as a 500. It now returns a 422 with a field error.

**Rate limiting.** The inquiry endpoint is limited to 5 submissions per 15 minutes per IP via Symfony's rate limiter. Adjust in `config/packages/rate_limiter.yaml`.

**CSRF.** Session-backed, so the form works without JavaScript. Do not move `submit` into `stateless_token_ids` in `config/packages/csrf.yaml` unless a JS bundle is added that can fetch and inject the token.

**Assets.** Tailwind is compiled to `public/build/app.css` by `npm run build`, which also copies `site.js` and `funnel.js` into `public/build/`. No CDN, no external font requests. `public/build/` is gitignored and regenerated at setup.

## Design tokens

Defined in `tailwind.config.js`. The semantic names are preferred for new work:

| Token | Value | Use |
|---|---|---|
| `brand` | `#0181C9` | Primary actions, links |
| `brand-deep` | `#016BA6` | Pressed/hover blue |
| `brand-ink` | `#1A2E38` | Headings, dark surfaces |
| `accent` | `#EC7600` | Eyebrows, highlights |
| `accent-deep` | `#C96300` | Pressed orange |
| `surface` | `#FFFFFF` | Page surface |
| `mist` | `#F4F7FA` | Light section wash |

The legacy names `regal` / `navy` / `canvas` are still load-bearing — `assets/funnel.js` and older templates reference them — and now carry the GDC palette. Prefer the semantic names in new work.

Bento cards signal hover with border colour and CTA letter-spacing only. They do not lift, scale, or cast shadows.
