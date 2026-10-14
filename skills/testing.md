# Testing

## Layout

`tests/` mirrors the `src/` structure and splits into two suites:

- **`tests/Unit/`** — no framework/kernel boot. Currently covers `Entity/`, `Security/`,
  `Service/`. Use for pure logic (entity behavior, isolated services with mocked dependencies).
- **`tests/Integration/`** — boots the Symfony kernel (via `self::getContainer()`). Mirrors most of
  `src/`: `Command/`, `Component/` (TwigComponents), `Controller/`, `EventSubscriber/`,
  `MessageHandler/`, `Pagination/`, `Repository/`, `Security/` (voters), `Service/`, `Workflow/`.
  Use for anything that touches the container, the database, routing, or rendering.
- **`tests/Mercure/`** — helpers/tests specific to the Mercure real-time layer.

Repository tests extend `RepositoryTestCase` (see
[`skills/doctrine-repositories.md`](doctrine-repositories.md) for the pattern: build fixtures with
helper factory methods like `makeCentre()`/`makeYear()`, persist, call the named repository method,
assert).

## PHPUnit configuration highlights (`phpunit.dist.xml`)

- `failOnDeprecation`, `failOnNotice`, `failOnWarning` are all enabled — deprecations and PHP
  notices/warnings fail the build, not just errors. Don't silence them; fix the root cause.
- `bootstrap="tests/bootstrap.php"`, `APP_ENV=test` forced.
- Coverage source is `src/` with Doctrine deprecation triggers wired in.

## Running tests

```bash
make test                          # full suite (php bin/phpunit)
vendor/bin/phpunit path/to/Test.php            # a single file
vendor/bin/phpunit --filter testMethodName     # a single test method
```

The release procedure ([`skills/releasing.md`](releasing.md)) requires the full suite to pass
before bumping the version — don't skip failures.

## Static analysis

```bash
vendor/bin/phpstan analyse
```

Runs the standard rules plus the project-specific
`ForbidGenericDoctrineMethodsRule` (see [`skills/doctrine-repositories.md`](doctrine-repositories.md)).
Configuration: `phpstan.dist.neon`.

## Migrations against real databases

PHPUnit runs on SQLite, which hides engine-specific migration bugs (2.9.0 shipped a MySQL migration that
failed because the foreign key must be dropped before its index). Whenever a release adds or changes
migrations, run — with Docker available:

```bash
scripts/check-migrations.sh            # or: make check-migrations   (pg | mysql | mariadb | all)
```

For PostgreSQL 16, MySQL 8 and MariaDB 11 it migrates up to the previous version with real rows, migrates up,
checks the data moved, migrates down, up again, and prints the schema diff against the ORM mapping. The
script also loads the demo fixtures and checks the offering counters (`countByFamily`, `countByProgramme`,
`countByLevel`): they once used `IN (:entities)`, which matched nothing on MySQL and migrated SQLite because
ids are stored as binary there — the in-memory tests did not catch it, so **never pass arrays of entities to
`IN (:param)`; compare one by one with an explicit `'uuid'` type**. The seed data and assertions in that script are specific to the stay/programme migration of 2.9.0: when a future
migration needs checking, adapt the seed and the checks. It also deletes an academic year with data through
`AcademicYearEraser` on every engine (SQLite is lenient with foreign keys, so a delete that works in the unit
tests can still fail on PostgreSQL/MySQL).

## Browser tests and accessibility audit

PHPUnit cannot exercise the Stimulus controllers or the inline scripts of the Live Components. `make e2e`
(`scripts/e2e/run.sh`) reuses the isolated screenshot environment (SQLite + fixtures + local server) and runs
`scripts/e2e/e2e.mjs` with Playwright: bulk selection, quick filters, remembered preferences, the copies dialog,
the ⌘K palette, and an axe-core audit (WCAG 2.0/2.1 A and AA) of the key pages. Serious or critical axe findings fail
the run. Add a test or a page to the `TESTS` / `A11Y_PAGES` arrays in that file. Pitfalls found so far:
`opacity-*` on a container lowers the text contrast (dim with a background colour instead), fade-in animations
falsify contrast measurements (the audit waits for them), and a TomSelect `<select>` needs its own `aria-label`
because TomSelect moves the `<label for>` to its generated control.
