# GDC Web App

Corporate front-end for Global Dynamics Consulting. Symfony 7.4 LTS, Doctrine ORM 3, Twig, Tailwind CSS compiled locally.

## Requirements

- PHP 8.2+ (tested on 8.4)
- Composer 2
- Node 18+ (only to build CSS)

## Setup

```bash
composer install
npm install && npm run build:css
php bin/console doctrine:schema:create
php bin/console doctrine:fixtures:load
php -S 127.0.0.1:8000 -t public public/index.php
```

Then open http://127.0.0.1:8000

On Pinokio, run `start.json` instead: it performs all four steps and opens the browser.

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
| GET | `/training/{slug}` | `app_training_show` |
| POST | `/training/{slug}/inquiry` | `app_inquiry_submit` |

The `{slug}` parameter is constrained to `[a-z0-9-]+`, so malformed input never reaches the database.

## Tests

```bash
php bin/console doctrine:schema:create --env=test
php vendor/bin/phpunit
```

Ten functional tests cover rendering, the bento matrix, XSS escaping, SQL injection attempts, 404 handling, form persistence, validation failures, and rate limiting.

## Architecture notes

**Escaping.** Twig autoescaping is on for `.html.twig`. No `|raw` is applied to any database-sourced value. `program.description` is rendered with `whitespace-pre-line` rather than raw HTML, so newlines survive without opening an injection path.

**Validation.** `CourseInquiry::$trainingProgram` is `NOT NULL` at the database level and carries `Assert\NotNull`. Without that constraint a tampered submission reached the database and threw `NotNullConstraintViolationException` as a 500. It now returns a 422 with a field error.

**Rate limiting.** The inquiry endpoint is limited to 5 submissions per 15 minutes per IP via Symfony's rate limiter. Adjust in `config/packages/rate_limiter.yaml`.

**CSRF.** Session-backed, so the form works without JavaScript. Do not move `submit` into `stateless_token_ids` in `config/packages/csrf.yaml` unless a JS bundle is added that can fetch and inject the token.

**Assets.** Tailwind is compiled to `public/build/app.css` by `npm run build:css`. No CDN, no external font requests. `public/build/` is gitignored and regenerated at setup.

## Design tokens

Defined in `tailwind.config.js`:

| Token | Value | Use |
|---|---|---|
| `regal` | `#1E3A8A` | Accents, eyebrow text, CTA |
| `navy` | `#0F172A` | Headings, body text |
| `canvas` | `#F8FAFC` | Page background |
| `max-w-7xl` | `1280px` | Global container |

Bento cards signal hover with border colour and CTA letter-spacing only. They do not lift, scale, or cast shadows.
