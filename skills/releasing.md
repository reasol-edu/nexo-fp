# Releasing a new version

Follow these steps **in this exact order** every time a new version (patch, minor, or major) is
published.

## 1. Run the local test suite

```bash
php -d memory_limit=512M bin/phpunit
```

Do not continue if there are failures.

## 2. Update `config/services.yaml`

- `app.version`: the new version string (e.g. `"2.5.1"`)
- `app.pub_date`: today's date as `"YYYY-MM-DD"`

This is the **single source of truth** for the version/date shown everywhere else (see step 5).

## 3. Update `CHANGELOG.md`

- Move the entries from `[Unreleased]` into a new `[X.Y.Z] - YYYY-MM-DD` section.
- English section headers (`Added`, `Changed`, `Fixed`…), Spanish entry content — see
  [`skills/committing-changes.md`](committing-changes.md#changelog).

## 4. Update `README.md`

- Update the version in the header (`<strong>vX.Y.Z</strong>`).
- **Do not skip this.** It was missed in release 1.1.0 (README stayed on v1.0.4) and had to be
  fixed afterwards.

## 5. Presentation, manual, and web — automatic, no action needed

Everything is derived from `config/services.yaml` (`app.version`, `app.pub_date`) via the `VERSION`
and `PUB_DATE` variables computed in the `Makefile` (`PUB_DATE` reformats `YYYY-MM-DD` →
`DD/MM/YYYY`):

- **Presentation** (`docs/slides/nexo-fp.md`): the source file carries `{{VERSION}}`/`{{PUB_DATE}}`
  placeholders in the front-matter `footer:` and on the cover slide. The `slides` Make target
  substitutes them into a gitignored temporary `_build.md` before compiling with Marp. **Never**
  edit the version by hand in that file, and always generate with `make slides` — running `marp`
  directly on the `.md` would show the raw placeholders.
- **Manual (PDF)**: the `docs-pdf` target passes pandoc `--metadata date="Versión $(VERSION) ·
  $(PUB_DATE)"`, rendered into `#title-block-header > .date` (styled by
  `docs/manual/assets/print.css`).
- **Manual (web)**: the `docs-web`/`docs-serve` targets export `MANUAL_COPYRIGHT="Nexo FP ·
  versión $(VERSION) · $(PUB_DATE)"`, read by MkDocs Material's `copyright: !ENV [...]` and shown
  in the page footer.

Since everything derives from step 2, there is **nothing to edit here** — just regenerate with
`make slides docs-pdf docs-web` (CI does this on every release) and confirm the right version/date
appear in the output.

## 6. Commit

```bash
git add config/services.yaml CHANGELOG.md README.md
git commit -m "Versión X.Y.Z"
```

No need to touch `docs/slides/nexo-fp.md` on every release (it carries fixed placeholders) —
include it only if its content actually changed.

## 7. Tag

```bash
git tag vX.Y.Z
```

## 8. Push branch and tag

```bash
git push && git push origin vX.Y.Z
```

Pushing a `v*` tag triggers `.github/workflows/build.yml`, which builds standalone FrankenPHP
binaries for Linux (x86_64/aarch64), macOS (x86_64/arm64), and Windows (x86_64), and
`.github/workflows/docker.yml` for the Docker image. `.github/workflows/tests.yml` runs on regular
pushes/PRs.

## Why this order matters

`app.version` is used in the UI; the Git tag marks the exact point of the release in history.
Deriving the presentation/manual/web version and date from `services.yaml` (single source of
truth) prevents publishing materials that state a different version than the software — the
presentation once lagged at v2.0.1 while the app had moved on, and had to be synced by hand before
this mechanism existed.
