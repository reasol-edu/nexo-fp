# Committing changes

The full commit-message convention for this repository. `CONTRIBUTING.md` has the external,
Spanish-language version of this same convention aimed at outside contributors; this file is the
agent-facing reference.

> **Note:** the format below (types, scopes, structure) is described here in English, but the
> **commit description itself must be written in Spanish** — that's the repository's actual
> convention, verified against `git log`.

## Format

```
<type>[(<scope>)][!]: <short description in Spanish, lowercase, ≤70 chars>

[optional body]

[Closes #N | Refs #N]
```

## Types

| Type | When to use it |
|---|---|
| `feat` | New functionality visible to the user or the system — new command, endpoint, entity, field, or constraint |
| `fix` | Fixes incorrect or unexpected behavior |
| `chore` | Maintenance with no impact on business logic: dependency bumps (`chore(deps)`), CI, tool configuration |
| `refactor` | Restructuring with no change in observable behavior — including reorganizing or renaming existing model elements (embeddables, `EXTRA_LAZY`, `readonly`, removing a redundant field, merging entities) |
| `test` | Changes exclusively to the test suite (no production code touched) |
| `docs` | Changes exclusively to documentation (README, CHANGELOG, comments…) |

### `feat(model)` vs `refactor(model)` vs `fix(model)` — the key distinction

- **`feat(model)`** — adds a new entity, field, relation, or constraint that expands what the
  system can represent.
- **`refactor(model)`** — reorganizes or renames what already exists without expanding capacity
  (embeddables, laziness hints, `readonly`, dropping a redundant field, merging entities).
- **`fix(model)`** — corrects a bug in the model.

`chore(model)` is **not** a valid combination for functional model changes — `chore` is reserved
for maintenance with zero business impact. If a change adds, fixes, or restructures model
behavior, it's `feat`/`fix`/`refactor`, never `chore`.

Before committing, ask: does it add something new? → `feat`. Does it fix something broken? →
`fix`. Does it reorganize without changing behavior? → `refactor`. Does it only touch
tooling/deps/config? → `chore`.

## Scopes (optional but recommended)

Technical layers:

| Scope | Layer |
|---|---|
| `model` | Doctrine entities and domain model |
| `migrations` | Database migrations |
| `command` | Console commands |
| `i18n` | Translations / internationalization |
| `security` | Security configuration |
| `dist` | Distribution / build scripts and configuration |
| `ci` | Continuous integration configuration |
| `deps` | Project dependencies |
| `docs` | Documentation |

Domain areas:

| Scope | Section |
|---|---|
| `stays` | Stays and training positions |
| `companies` | Companies, work centres, and workers |
| `centre` | Educational centre: teachers, students, and course offerings |
| `admin` | Global administration |

Combine scopes with `/` when a change crosses dimensions (e.g. `stays/i18n`). Omit the scope when
the change touches several layers at once.

## Breaking changes

Add `!` immediately after the type (and scope, if any) when the change is incompatible with
previous versions: migrations that alter existing columns, changes to console command signatures,
schema changes that require manual steps on deploy.

```
feat(model)!: cambiar tipo de la columna status a enum nativo de PostgreSQL
fix!: el comando app:create-admin ahora exige especificar el nombre de usuario
```

## Issue references

- `Closes #N` — closes the issue automatically on merge.
- `Refs #N` — references without closing (useful for partial commits).

## Examples

```
feat(stays): filtro por estado en el listado de puestos formativos

Closes #42
```

```
fix(companies): los docentes de enlace no podían editar centros de trabajo

Refs #38
```

```
chore(deps): actualizar Symfony a 8.2
refactor(stays/i18n): unificar cadenas de estado de puesto en un solo dominio
test(centre): cubrir el caso de importación con CSV en codificación Windows-1252
docs: documentar el modo de despliegue con Docker en el README
```

## CHANGELOG

User-visible changes are documented in the `[Unreleased]` section of `CHANGELOG.md`, following
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/):

- Section headers (`Added`, `Changed`, `Deprecated`, `Removed`, `Fixed`, `Security`) stay in
  **English**.
- Entry content is written in **Spanish**, aimed at the application's end user, without jargon.
- New entries are added **at the top** of their section.
- Breaking commits (`!`) must have an entry under `Fixed` or `Changed` as appropriate, stating
  whether a manual step is required when upgrading.
- Internal-only changes (`ci`, `test`, `docs`, `refactor` with no visible impact) **do not need** a
  CHANGELOG entry.
- Update the CHANGELOG **manually, before** running `git commit`, and stage it in the same commit
  (a `commit-msg` hook that edited the CHANGELOG after commit creation was tried and removed —
  it modified the CHANGELOG after the commit already existed, not as part of it).
