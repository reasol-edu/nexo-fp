# AGENTS.md — Nexo FP

Entry point for coding agents working in this repository. Keep this file short — it is a map, not
the territory. Detailed, topic-specific knowledge lives under [`skills/`](skills/); this file tells
you what exists and where to go deeper.

## What this project is

**Nexo FP** is a Symfony web application that manages the planning and assignment of Spanish
vocational-training (FP) work-placement stays (*"Fase de Formación en Empresa u Organismo
Equiparado" / FFEOE*). It centralizes students, companies, training positions and tutors, and
tracks the assignment process from the creation of a position to its registration in the
Andalusian education administration system (Séneca).

Key product traits that shape the codebase:

- **Multi-tenant**: one server can host several educational centres (`EducationalCentre`) with
  fully separated data. Teachers pick an active centre at login and only see that centre's data.
  Global administrators manage all centres from the Administration section.
- **Real-time collaboration**: several people can edit the positions of the same stay at once;
  the screen updates live via Mercure, highlighting changes and warning about edit conflicts
  instead of silently overwriting.
- **Two roles**: `ROLE_TEACHER` and `ROLE_ADMIN` (see `config/packages/security.yaml`).

See [`skills/architecture.md`](skills/architecture.md) for the full domain model and technical
architecture.

## Stack

- PHP 8.4+, Symfony 8.1, Doctrine ORM 3 (PostgreSQL / MySQL / MariaDB / SQLite)
- Doctrine Migrations, Doctrine Fixtures Bundle, Symfony Messenger, Symfony Workflow, Mercure
- Frontend: Symfony AssetMapper (no npm bundler for app code), Tailwind via
  `symfonycasts/tailwind-bundle`, Stimulus controllers, Symfony UX (TwigComponents, Live
  Components, Autocomplete, Icons)
- PDF/export: mpdf, openspout
- Tests: PHPUnit; static analysis: PHPStan (with a custom project rule, see below)

## Repository layout

```
src/
  Entity/            Doctrine entities (domain model)
  Repository/        The ONLY place allowed to query the ORM directly (see rule below)
  Controller/         incl. Controller/Admin/ for the Administration section
  Service/            Application services (mailers, exporters, tenant context, PDF, …)
  EventSubscriber/     Doctrine/Kernel event subscribers (audit log, tenant context, …)
  Security/Voter/      Authorization voters (Company, EducationalCentre, Stay)
  Workflow/            Symfony Workflow guard subscribers (TrainingPosition state machine)
  Twig/Components/     TwigComponents / Live Components, incl. Admin/ and Company/ subfolders
  Command/             Console commands
  Message/, MessageHandler/  Symfony Messenger async messages
  Autocomplete/        symfony/ux-autocomplete providers
  Pagination/          Custom paginator
  PHPStan/Rules/        Project-specific PHPStan rules
  DataFixtures/         Doctrine fixtures (demo data)
tests/
  Unit/                No framework boot; mirrors src/ (Entity, Security, Service)
  Integration/          Boots the kernel; mirrors src/ (Command, Component, Controller,
                         EventSubscriber, MessageHandler, Pagination, Repository, Security,
                         Service, Workflow)
  Mercure/              Mercure-specific test helpers
docs/
  manual/              User manual (Markdown, source of truth for behavior/screens)
  slides/              Marp introduction presentation
config/packages/*.yaml Symfony bundle configuration
```

## Core commands

| Command | Purpose |
|---|---|
| `make test` | Run the full PHPUnit suite (`php bin/phpunit`) |
| `vendor/bin/phpstan analyse` | Static analysis, including the custom repository rule |
| `make fixtures` | Load demo fixtures (`doctrine:fixtures:load --append`) |
| `make migrate` | Run Doctrine migrations |
| `make setup` | Run `app:setup` (initial app setup) |
| `make dev` / `make dev-stop` | Start/stop local dev environment (DB + Mercure hub + `symfony serve`) |
| `make docs` / `docs-pdf` / `docs-web` / `docs-serve` | Build the user manual (PDF / MkDocs web) |
| `make slides` | Build the Marp presentation to PDF |

## Hard rules — do not violate

1. **Never call generic Doctrine retrieval methods outside `App\Repository\*`.**
   `EntityManagerInterface::find()/getRepository()` and `ManagerRegistry::getRepository()` are
   banned everywhere else and enforced by a custom PHPStan rule
   (`src/PHPStan/Rules/ForbidGenericDoctrineMethodsRule.php`). Always inject the concrete
   repository and add a named method expressing intent. Details:
   [`skills/doctrine-repositories.md`](skills/doctrine-repositories.md).
2. **Never hand-edit `public/assets/`.** It's compiled AssetMapper output; in development it
   shouldn't exist at all (it takes priority over live assets and will serve stale JS). If it's
   stale, `rm -rf public/assets var/cache/dev`. Details:
   [`skills/frontend.md`](skills/frontend.md).
3. **Overlapping stays are legitimate, not a bug.** A teaching can have several stays that
   overlap in time because not every stay includes every student in a group. Never propose
   overlap validation as a fix or improvement.
4. **Demo/fixture data is always invented**, never real institution data. See
   [`skills/fixtures-and-demo.md`](skills/fixtures-and-demo.md).
5. **Commit descriptions and CHANGELOG entry bodies are written in Spanish** (this repository's
   convention, even though this file and `skills/` are in English) — see
   [`skills/committing-changes.md`](skills/committing-changes.md).

## Skills index

- [`skills/architecture.md`](skills/architecture.md) — domain model, multi-tenancy, security
  voters, the TrainingPosition state machine, real-time sync, UI component layer.
- [`skills/doctrine-repositories.md`](skills/doctrine-repositories.md) — the repository-only
  data-access rule, in full.
- [`skills/testing.md`](skills/testing.md) — Unit vs Integration layout, how to run tests and
  PHPStan.
- [`skills/committing-changes.md`](skills/committing-changes.md) — commit message convention
  (types, scopes, breaking changes) and CHANGELOG rules.
- [`skills/releasing.md`](skills/releasing.md) — the exact release procedure, step by step.
- [`skills/documentation.md`](skills/documentation.md) — the manual/slides build pipeline and the
  documentation screenshot harness.
- [`skills/fixtures-and-demo.md`](skills/fixtures-and-demo.md) — demo data conventions.
- [`skills/frontend.md`](skills/frontend.md) — AssetMapper, Tailwind, Stimulus, TwigComponents.
- [`skills/security-notes.md`](skills/security-notes.md) — known-open security item (CSP).

## Where else to look

- [`docs/manual/`](docs/manual/) — the user-facing reference manual; source of truth for what the
  application does and how each screen behaves.
- [`CHANGELOG.md`](CHANGELOG.md) — release history (English section headers, Spanish content).
- [`CONTRIBUTING.md`](CONTRIBUTING.md) — the external-contributor version of the commit/PR
  conventions (in Spanish); `skills/committing-changes.md` is the agent-facing superset.
- [`DEMO.md`](DEMO.md) — reference for demo users, centres and stay scenarios loaded by fixtures.
