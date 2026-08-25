# Architecture

## Domain model

Core entities in `src/Entity/`:

- **`EducationalCentre`** — a tenant: an educational institution. Everything else hangs off a
  centre, directly or transitively.
- **`AcademicYear`** — a school year scoped to a centre.
- **`ProfessionalFamily`** → **`Programme`** → **`ProgrammeYear`** — the curriculum hierarchy
  (family → cycle/degree → year of that cycle), scoped to a centre.
- **`Group`** — a class group of students within a `ProgrammeYear`.
- **`Student`** — belongs to a group.
- **`Teacher`** — the authenticated user (see Security below). Has a role and can be linked to
  programme years as coordinator, family head, or liaison teacher ("docente de enlace") with
  companies.
- **`Company`** → **`Workcenter`** → **`Worker`** — a company, its work centres (physical
  locations), and the workers who can supervise students there. `CompanyAudit` tracks changes to
  company data.
- **`Stay`** ("estancia") — a placement period tied to a `ProgrammeYear` (e.g. "FFEOE DAW 2026
  (2nd term)"), with a date range.
- **`TrainingPosition`** ("puesto formativo") — a single student's assignment within a `Stay` to a
  `Workcenter`/`Worker`, with a lifecycle state (see Workflow below). **Overlapping stays/positions
  in time are legitimate by design** — a teaching can run several overlapping stays because not
  every stay includes every student in a group. Never propose overlap validation.
- **`Comment`** — free-text notes attached to domain objects.
- **`ActivityLog`** — audit trail of user actions (see EventSubscriber below).
- **`SettingDefinition`** / **`SettingType`** / **`GlobalSettingValue`** / **`CentreSettingValue`**
  / **`TeacherSettingValue`** — a hierarchical settings system: a setting is defined once
  (`SettingDefinition`) and can have a global default, a per-centre override, and a per-teacher
  override, resolved in that order.

## Multi-tenancy

`src/Service/TenantContext.php` (behind `TenantContextInterface`) holds the "current centre" for
the request. `src/EventSubscriber/TenantContextSubscriber.php` resolves it (from the
authenticated teacher's selection) at the start of each request. Repository methods that should be
tenant-scoped take the centre explicitly rather than reading a global — check existing repository
methods (e.g. `findByCentreOrderedByName`) for the pattern.

## Security

- Two roles: `ROLE_TEACHER`, `ROLE_ADMIN` (`config/packages/security.yaml`).
- Provider: `Teacher` entities, authenticated via a custom authenticator
  (`App\Security\TeacherAuthenticator`), with login throttling (5 attempts / 15 min) and
  `switch_user` restricted to `ROLE_ADMIN`.
- `access_control` highlights: `/admin/*` requires `ROLE_ADMIN` except a few sub-paths under
  `/admin/centros/{id}/(estudiantes|docentes-curso|familias)` which only need `ROLE_TEACHER`;
  everything else under `/` requires at least `ROLE_TEACHER`.
- Fine-grained authorization is done with **Voters** (`src/Security/Voter/`):
  `CompanyVoter`, `EducationalCentreVoter`, `StayVoter`. Use these (via
  `#[IsGranted]`/`isGranted()`) rather than ad-hoc role checks for anything centre- or
  ownership-scoped.

## TrainingPosition state machine

Defined in `config/packages/workflow.yaml` as a Symfony Workflow `state_machine` named
`training_position`, marking stored on the `state` property:

```
places: DRAFT, PENDING, DONE
transitions:
  to_draft:   PENDING|DONE -> DRAFT
  to_pending: DRAFT|DONE   -> PENDING
  to_done:    DRAFT|PENDING -> DONE
```

`src/Workflow/TrainingPositionGuardSubscriber.php` guards these transitions with business rules
(e.g. required fields before moving to a given state). When adding a new business constraint on
state changes, extend this subscriber rather than checking state ad-hoc in controllers.

## Real-time sync (Mercure)

`src/Service/StayRealtimeNotifier.php` publishes changes to the Stay screen over Mercure so that
everyone viewing the same stay sees updates live. The frontend counterpart is the
`mercure_sync` Stimulus controller (`assets/controllers/mercure_sync_controller.js`). In local
development, `symfony server:start` auto-detects the dev Mercure hub container and injects
`MERCURE_URL`/`MERCURE_PUBLIC_URL` — no manual config needed (see project README for the
`compose.dev.yaml` overlay). The Mercure channel only ever carries a "something changed" signal,
never actual data, so it can run in anonymous/open mode in dev without a data-exposure concern.

## Async processing

Symfony Messenger (`src/Message/`, `src/MessageHandler/`) handles: activity-log writes
(`ActivityLogMessage`/`Handler`), activity-log purging (`PurgeActivityLogMessage`/`Handler`), and
signature reminder emails (`SendSignatureRemindersMessage`/`Handler`, dispatched by
`src/Service/SignatureReminderDispatcher.php`, likely on a schedule — see
`src/Command/SendRemindersCommand.php` and Symfony Scheduler).

## Auditing

`src/EventSubscriber/ActivityLogSubscriber.php` and `CompanyAuditSubscriber.php` are Doctrine
event subscribers that record changes into `ActivityLog`/`CompanyAudit` automatically —
`src/Service/ActivityLogService.php` is the read/write API for that log. Don't add manual
audit-logging calls in controllers/services for entities already covered by these subscribers.

## UI layer

- **TwigComponents / Live Components** (`src/Twig/Components/`, mirrored templates under
  `templates/components/`): stateful, server-rendered UI pieces, e.g. `StayCalendarComponent`,
  `StayDetailComponent`, `StayListComponent`, `NotificationBellComponent`, `SettingsComponent`,
  plus `Admin/` (list components for centres, teachers, students, activity log, professional
  families) and `Company/` (company list, workcenter/worker forms) subfolders. Overlapping lanes
  in `StayCalendarComponent`'s rendering are an intentional feature (mirrors the overlapping-stays
  domain rule above), not a rendering bug.
- **Stimulus controllers** (`assets/controllers/`): thin client-side behavior — dropdowns,
  sidebar, confirm dialogs, rich-text editor (Quill) wiring, Mercure sync, command palette, CSRF
  protection helper, form submit/download feedback, password visibility, filter persistence,
  go-to-page. See [`skills/frontend.md`](../skills/frontend.md).
- **Autocomplete** (`src/Autocomplete/`): `symfony/ux-autocomplete` providers for teacher pickers
  (`TeacherAutocompleter`, `TeacherCentreAutocompleter`, `TeacherLiaisonAutocompleter`).
