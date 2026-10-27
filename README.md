# Recruit Aid

**A complete Applicant Tracking System (ATS) for recruitment agencies.**

Part of the [Corporate Aid Solutions](#corporate-aid-solutions) suite.
Sold as a white-labelled product to agencies who own and rebrand their
install.

**Current version:** `0.6.0-alpha`
**Status:** Active development (pre-launch)

---

## What It Is

Recruit Aid helps recruitment agencies manage:

- Client organisations and contacts
- Engagements (recruitment contracts with scope and capacity)
- Vacancies (jobs being filled, with per-vacancy pipelines)
- Candidates and applications *(in progress)*
- Assessments, interviews, offers *(planned)*

The platform is designed to be configured by the customer — custom
fields on any entity, self-validating input types, configurable
pipeline stages — without needing a developer.

**Ownership model:** Each customer installs one instance on their own
hosting, rebrands it, and operates it. Corporate Aid Solutions has zero
runtime involvement after the license key is issued.

---

## Corporate Aid Solutions

Recruit Aid is product #1 in the Corporate Aid Solutions suite.

| Tier | Format | Examples |
|---|---|---|
| **Tier 1** — Company | — | Corporate Aid Solutions (vendor) |
| **Tier 2** — Functional modules | `{Function} Aid` | Recruit Aid, HR Aid, CRM Aid, … |
| **Tier 3** — Industry verticals | `{Industry} Aid` | Farm Aid, Clinic Aid, Supermarket Aid, … |
| **Tier 0** — Integration | — | Corporate Connect (customer-local) |

Each module can be purchased independently. Full taxonomy:
[`docs/NAMING-CONVENTION.md`](docs/NAMING-CONVENTION.md).

---

## Requirements

**For development:**
- Windows 11 with WSL (Ubuntu)
- [Laragon](https://laragon.org/) with Apache :8080 and MySQL :3306
- PHP 8.2+
- Composer 2.10+
- No Node.js, no npm, no Docker

**For production (cPanel hosting):**
- PHP 8.2+ with `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `json`,
  `ctype`, `tokenizer`, `dom`, `gd`, `curl`, `zip`
- MySQL 8.0+ or MariaDB 10.6+
- Apache with `mod_rewrite`
- Free SSL (AutoSSL)
- Cron support
- SMTP email account

No SSH, no VPS, no Docker required.

---

## Quick Start (Development)

### 1. Clone or restore

```bash
cd /mnt/c/projects/recruitaid
# git clone <repo> .  # when Git is set up
# or restore from a milestone backup:
# tar -xzf ~/recruitaid-backups/recruitaid-milestone14-*.tar.gz
```

### 2. Install dependencies

```bash
# In Laragon Terminal
cd C:\projects\recruitaid
composer install
composer dump-autoload
```

### 3. Configure

Two config files (both gitignored):

- `config/local.php` — web app credentials (uses the limited DB user)
- `config/cli.php` — CLI credentials (uses the elevated DB user)

See `docs/ARCHITECTURE.md` §6 for the config split rationale.

### 4. Create the database

```sql
CREATE DATABASE corporateaid_recruit
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

Grant the app user permissions:

```sql
GRANT SELECT, INSERT, UPDATE, DELETE
  ON corporateaid_recruit.*
  TO 'recruitaid_app'@'localhost';
```

### 5. Migrate and seed

```bash
# In Laragon Terminal
cd C:\projects\recruitaid
php bin\migrate.php
php bin\seed.php
```

### 6. Serve

Open `http://localhost:8080` in a browser.

**Default admin credentials (seeded):**

| Email | Password | Role |
|---|---|---|
| `admin@recruitaid.test` | `AdminPass!2026` | super_admin |
| `researcher@recruitaid.test` | `ResearchPass!2026` | researcher |

**These are for local development only.** Production installs collect
the first admin's credentials during setup.

---

## Documentation

All documentation lives in `docs/`. Start here:

| Document | Purpose |
|---|---|
| [`docs/ABOUT.md`](docs/ABOUT.md) | Product overview, principles, feature set |
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | How the system is built |
| [`docs/SPECIFICATION.md`](docs/SPECIFICATION.md) | 17 ADRs — the design decisions |
| [`docs/NAMING-CONVENTION.md`](docs/NAMING-CONVENTION.md) | Naming rules across the suite |
| [`docs/CHANGELOG.md`](docs/CHANGELOG.md) | Chronological version history |
| [`docs/CHANGELOG-middleware.md`](docs/CHANGELOG-middleware.md) | Deep dive: onion middleware |
| [`docs/RENAME-RECORD.md`](docs/RENAME-RECORD.md) | Deep dive: naming convention execution |
| [`docs/REMEMBER.md`](docs/REMEMBER.md) | Current session state — resume point |

---

## Environment Cheat Sheet

**Laragon Terminal** for PHP, Composer, MySQL commands.

**WSL (Ubuntu)** for file operations — `cat`, `ls`, `grep`, `python3`.

**HeidiSQL** for database inspection (connects to `127.0.0.1`).

**Mailpit** for local email testing (SMTP on `:1025`, web UI on `:8025`).

**Critical:** WSL has no PHP, and cannot reach MySQL from its network
namespace. Always use Laragon Terminal for PHP and DB commands.

---

## Everyday Commands

**Migrate (Laragon Terminal):**

```cmd
cd C:\projects\recruitaid
php bin\migrate.php
php bin\migrate.php status
```

**Seed (Laragon Terminal):**

```cmd
php bin\seed.php
```

**Regenerate the autoloader after adding a class (Laragon Terminal):**

```cmd
composer dump-autoload
```

**Tail the application log (WSL):**

```bash
tail -20 /mnt/c/projects/recruitaid/storage/logs/app.log
```

**Find a log entry by error reference ID (WSL):**

```bash
grep ERR-4a2f9c /mnt/c/projects/recruitaid/storage/logs/app.log
```

**Grep for a specific table or namespace (WSL):**

```bash
grep -rn 'core_users' /mnt/c/projects/recruitaid/app/
grep -rn 'CorporateAid\\Recruit' /mnt/c/projects/recruitaid/app/
```

---

## Project Structure

```
app/
  Core/           Framework (no business knowledge)
  Platform/       Shared services, models, middleware
  Recruit/        Recruit Aid module code
  Helpers/        Global helper functions

config/
  app.php         Shared defaults (committed)
  local.php       Per-install overrides (gitignored)
  cli.php         CLI database credentials (gitignored)

database/
  migrations/     Versioned SQL schema changes
  seeders/        Initial data (roles, permissions, currencies, admin)

docs/             All documentation

public/           Web root (only this is exposed by Apache)
  index.php       Front controller
  assets/         CSS, JS

resources/
  views/          PHP templates

routes/           URL → controller mapping
  web.php         Main authenticated + public routes
  auth.php        Login, logout, password reset

storage/          Outside web root
  logs/           Application logs
  uploads/        User-uploaded files (blocked from direct access)
  cache/          Cache directory
  backups/        Local DB dump snapshots
```

---

## Architecture in One Paragraph

A custom minimal MVC framework. HTTP requests enter through
`public/index.php`, dispatch through `Router` to a controller, wrapped
in an onion-style middleware pipeline (auth, csrf, permission, rate
limit, audit). Controllers are thin — they translate HTTP into service
calls. Services contain business logic. Repositories contain all SQL.
Models are property bags. Every state-changing request writes a row to
`core_audit_logs` automatically. Business events are written to
`core_activities` by services. The middleware model is onion (each
middleware receives the request and a `$next` callable), which enables
post-controller behavior. Full details:
[`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

---

## Development Doctrine

These rules were learned the hard way. See `docs/REMEMBER.md` for the
full list with context.

1. **Full-file rewrites only** — no patches to code. If a file needs
   changes, rewrite it in full.
2. **No hand-edits for diagnostics** — temporary debug code goes in via
   full-file rewrite or a WSL Python injector that lints afterward.
3. **Read dependencies before rewriting** — read the file, its callers,
   and one sibling for pattern reference.
4. **Command context matters** — heredocs in WSL, PHP/Composer in
   Laragon. Never mix in one instruction block.
5. **`composer dump-autoload` after creating any new PHP class** —
   the autoloader is `--optimize --authoritative`.
6. **Migrations are immutable once shipped** — any change requires a
   new migration file.
7. **Always test the full loop** — "no error" is not the same as
   "works."

---

## Testing

Automated tests are not yet in place. Current verification is:

- `php -l` on every file (batchable in Laragon Terminal)
- Manual smoke tests after each feature ships
- Grep-based verification for rename and refactor passes
- Live database queries in HeidiSQL

Automated test infrastructure (PHPUnit, PHPStan, Playwright) is
planned for Tier 5.

---

## Deployment (Summary)

The full deployment flow is planned for a future release. In short:

1. Push code to a release branch
2. CI runs tests (when tests exist)
3. Deploy to staging, verify
4. Backup the production database
5. Deploy code
6. Run migrations
7. Health check
8. Roll back if the health check fails

**Never edit PHP files directly on production via FTP.** Always deploy
a tested release.

---

## License

Proprietary. Sold as a white-labelled product to recruitment agencies.

Each install is licensed per-module. The customer owns the code, the
data, and the infrastructure.

---

## Contributing

This is a proprietary project. Contributions are handled internally
by Corporate Aid Solutions.

---

## Support

- **Documentation:** start with `docs/README.md` (or `docs/ABOUT.md`)
- **Bugs:** capture the error reference ID (`ERR-xxxxxx`) shown on
  error pages, then `grep` the app log for that ID
- **Feature requests:** tracked in the project backlog
  (`docs/REMEMBER.md` §Known Issues)