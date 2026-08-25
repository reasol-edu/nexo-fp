# Fixtures and demo data

## Always use DoctrineFixturesBundle

Demo data is loaded exclusively via `doctrine/doctrine-fixtures-bundle` (`src/DataFixtures/
AppFixtures.php`), never a custom Symfony command. This was decided explicitly against a plan that
proposed a bespoke `app:load-demo` command — don't reintroduce that pattern.

Run with:

```bash
make fixtures
# equivalent to: php bin/console doctrine:fixtures:load --no-interaction --append
```

`--append` is required and intentional, not a shortcut: the schema has a circular foreign key
between `educational_centre` and `academic_year` that Doctrine's ORM purger cannot resolve (the
fixture class does its own cleanup, in the correct order, via `wipeDatabase()`), and
`setting_definition` holds reference data seeded by migrations that must never be wiped when
reloading demo data.

Other ways to load fixtures (see [`DEMO.md`](../DEMO.md) for full detail):

- Native binary distribution: `demo.sh` / `demo.bat` / `demo.command` / `demo.ps1` scripts (same as
  the normal start scripts, but auto-load fixtures).
- Environment variable `LOAD_FIXTURES=true` before starting the app (Docker or native binary).

## Never use real institution data

Fixture data for educational centres, companies, and people is **always invented**. Never use real
centre names, real centre codes, or any real institutional data.

- Centre codes follow the real Andalusian numbering scheme by province but with invented numbers,
  e.g. `23XXXXXX` for Jaén, `41XXXXXX` for Sevilla.
- Invent plausible but fictional names, e.g. "IES Ada Lovelace", "IES Monterrubio" (not real
  centres like "IES Oretania" or "IES Sotero Hernández", which appeared in an earlier plan and were
  explicitly rejected for this reason).

## What the demo dataset currently contains

See [`DEMO.md`](../DEMO.md) for the full, authoritative reference — it documents two demo centres
("IES Ada Lovelace" in Linares and "IES Monterrubio" in Utrera) each with a curriculum, ~30
teachers with varied roles (family head, coordinator, liaison teacher, plain teacher), ~12
companies with liaison-teacher assignments, and a deliberate set of stay/training-position
scenarios (past vs. current stay, vacant positions, positions missing a dual tutor, unsigned
positions nearing their deadline, finished positions, unassigned students) designed to exercise
every state and notification path in the UI. Update `DEMO.md` alongside `AppFixtures.php` if you
change the scenarios.

Login: global admin `admin`/`admin` (centre-independent); all other teachers use password
`ejemplo`.
