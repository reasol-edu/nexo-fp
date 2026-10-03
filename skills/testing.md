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
seed data and assertions in that script are specific to the stay/programme migration of 2.9.0: when a future
migration needs checking, adapt the seed and the checks.
