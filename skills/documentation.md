# Documentation

## The user manual

[`docs/manual/`](../docs/manual/) is the **source of reference** for what the application does and
how each screen behaves — written in Markdown, chapters `01-instalacion-y-requisitos.md` through
`12-glosario.md` plus `index.md`. It's compiled to two formats from the same content:

- **PDF**: `make docs-pdf` → `docs/manual/nexo-fp-manual.pdf`. Uses `pandoc` (Markdown → HTML, with
  `docs/pandoc-admonitions.lua` and `docs/pandoc-internal-links.lua` filters) and `npx pagedjs-cli`
  (headless Chromium, same engine as the slides PDF) to paginate the HTML with
  `docs/manual/assets/theme.css`/`print.css`. Requires `pandoc` and Node.js; reuses the system
  Chrome via `PUPPETEER_EXECUTABLE_PATH` if found.
- **Web** (with search): `make docs-web` → `docs/manual-site/`, or `make docs-serve` to preview at
  `http://127.0.0.1:8000`. Requires MkDocs Material (`pip install -r docs/manual/requirements.txt`).
- `make docs` runs both.

Version/date on both outputs come automatically from `config/services.yaml` — see
[`skills/releasing.md`](releasing.md). Published PDFs (manual + presentation) and the web manual
(as a ZIP) are attached to each GitHub Release, named with the version
(`nexo-fp-manual-vX.Y.Z.pdf`, etc.); the `make` targets above are for local preview only.

## The presentation

[`docs/slides/`](../docs/slides/) is a Marp presentation introducing Nexo FP. `make slides`
generates `docs/slides/nexo-fp.pdf` via `npx @marp-team/marp-cli` (no global install). See
[`docs/slides/README.md`](../docs/slides/README.md) for details and
[`skills/releasing.md`](releasing.md) for how its version placeholders work.

## Regenerating screenshots

`docs/manual/img/` and `docs/slides/img/` screenshots must be regenerated with a fully **isolated**
environment — never against the real database or `.env.local` (which holds real secrets).

### 1. Isolated environment (SQLite + fixtures)

```bash
export APP_ENV=dev APP_DEBUG=0 \
  DATABASE_URL="sqlite:///%kernel.project_dir%/var/screenshot.db" \
  MIGRATIONS_PATH=migrations/sqlite
rm -f var/screenshot.db
php bin/console doctrine:schema:create
php bin/console doctrine:fixtures:load --no-interaction
```

Fixtures create the "IES Ada Lovelace" centre, `admin`/`admin`, and a full set of
families/programmes/levels/groups with teachers and coordinators assigned.

### 2. Server (same environment variables)

```bash
rm -rf var/cache/dev   # APP_DEBUG=0 => Twig does not auto-recompile; clear cache after editing templates
php -S 127.0.0.1:8124 -t public/   # run in background
```

Use port **8124** specifically — never touch a user's existing process on 8123.

### 3. Playwright

- Installed under `/tmp/node_modules`; run scripts as `node /tmp/<script>.mjs` (the shell's cwd is
  already the project root, so relative paths like `docs/...` resolve).
- Log in as `admin`/`admin` → if redirected to `/centro`, select "Ada Lovelace" → `/mi-centro` →
  extract `centreId` with `/admin/centros/([a-f0-9-]{36})` → navigate to
  `/admin/centros/{id}/familias`.
- Drill into the curriculum tree via the `data-live-action-param="selectFamily|selectProgramme|
  selectLevel|selectGroup"` buttons.

### 4. Capture tricks

- Hide the Symfony debug toolbar: `.sf-toolbar, .sf-minitoolbar, #sfMiniToolbar, .sf-toolbarreset
  { display:none !important; }`.
- For Live Components using TomSelect, before capturing: hide `.ts-dropdown`, set
  `overflow-x:hidden` on `html`/`body`, and reset scroll —
  `document.querySelectorAll('*').forEach(e=>e.scrollLeft=0); window.scrollTo(0,0);
  document.activeElement?.blur();` — otherwise the left column gets clipped by a few pixels.
- **Manual**: viewport 1440×900, `deviceScaleFactor: 1` (matches existing images).
- **Slides**: `deviceScaleFactor: 2`.

### 5. Cleanup

```bash
pkill -f "php -S 127.0.0.1:8124"
rm -f var/screenshot.db
rm -rf var/cache/dev
# remove any temporary /tmp/*.mjs scripts
```

## Reusable local tooling: `var/claude/`

`var/claude/` (gitignored, not committed) is a scratch toolbox for recurring tasks. Before writing
a script for a recurring job (screenshots, demo data generation, etc.), check whether one already
exists there; if you create a new one, save it there too and add it to `var/claude/README.md`.
Existing example: `var/claude/screenshot-dashboard.mjs` regenerates `docs/manual/img/inicio.png`
(1440×900) end-to-end (temp SQLite + fixtures, login as `admin` on "IES Ada Lovelace", hide the
debug toolbar, capture the dashboard).
