# Frontend

## Asset pipeline

The app uses **Symfony AssetMapper** (`symfony/asset-mapper`), not a Node.js bundler, for
application JavaScript/CSS — dependencies are vendored under `assets/vendor/` (Quill,
TomSelect, lodash utilities, etc., listed in `importmap.php`) and served directly or compiled
ahead-of-time for production/distribution builds. Tailwind CSS is integrated via
`symfonycasts/tailwind-bundle`.

### Never hand-edit `public/assets/`

`public/assets/` is **compiled** AssetMapper output (`asset-map:compile`), generated only by CI for
the standalone binaries. In development it should not exist at all — if present, it takes priority
over the live assets served dynamically from `assets/`, which leads to a broken, inconsistent
state (stale cached JS, 404s on preloads, etc.).

**Why this matters:** in v1.4.0, a manual patch (editing a compiled
`controllers-<hash>.js` in place without changing its hash, and deleting a file still referenced
by `importmap.json`/`manifest.json`) left dev in a broken state — preload 404s on every page load
and browsers running stale cached JS, causing settings to silently show "Saved" without actually
saving. It cost a full debugging session to track down.

**Fix, always:** `rm -rf public/assets var/cache/dev` to force dynamic regeneration. Never
edit/copy files inside `public/assets/` directly.

## Stimulus controllers (`assets/controllers/`)

Thin client-side behaviors, each a small, focused controller:

| Controller | Purpose |
|---|---|
| `dropdown_controller.js` | Dropdown menus |
| `sidebar_controller.js` | Sidebar navigation |
| `confirm_controller.js` | Confirmation prompts before an action |
| `rich_editor_controller.js` | Quill rich-text editor wiring |
| `mercure_sync_controller.js` | Live updates via Mercure (see [`skills/architecture.md`](architecture.md#real-time-sync-mercure)) |
| `command_palette_controller.js` | Command palette / quick navigation |
| `csrf_protection_controller.js` | CSRF token handling helper |
| `form_submit_controller.js` | Form submission behavior |
| `download_feedback_controller.js` | UI feedback for file downloads |
| `password_visibility_controller.js` | Show/hide password fields |
| `filter_persist_controller.js` | Persist list filters across navigation |
| `goto_page_controller.js` | Pagination "go to page" input |
| `external_auth_controller.js` | External authentication flow support |
| `hello_controller.js` | Stimulus starter/demo controller |

## Symfony UX

- **TwigComponents + Live Components** (`symfony/ux-live-component`): server-rendered, stateful
  components under `src/Twig/Components/` — see
  [`skills/architecture.md`](architecture.md#ui-layer) for the inventory and patterns.
- **Autocomplete** (`symfony/ux-autocomplete`): teacher pickers in `src/Autocomplete/`.
- **Icons** (`symfony/ux-icons`): Heroicons under `assets/icons/heroicons`.

## CSP-relevant frontend quirks

Three frontend libraries have non-trivial requirements under a strict Content-Security-Policy —
relevant if you ever touch CSP headers. See [`skills/security-notes.md`](security-notes.md).
