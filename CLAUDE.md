# CLAUDE.md — seatplus/auth

Guidance for Claude Code working in this package on its own (e.g. opened as a
standalone Orca project). A standalone checkout does **not** inherit the core
app's `CLAUDE.md`, skills, or MCP — this file is the local pointer.

## What this is
**auth** — the security core of seatplus: EVE OAuth (SSO), the
role/affiliation/permission system, and SSO scope compliance. All security and
permission logic lives here, with full test + 100% type coverage and PHPStan.
It's third in the one-way chain `esi-client → eveapi → auth → web`; it may import
from `eveapi`/`esi-client` but **never** from `web`.

**When behaviour is unclear, `tests/` is authoritative** — prefer reading a test
over guessing. The wider project and the shared `.claude/skills/` live in
**[seatplus/core](https://github.com/seatplus/core)**, whose `CLAUDE.md` is the
source of truth. laravel-boost + the browser MCP only apply in the assembled core
app, not here.

## Domain quick map
- **Roles** extend Spatie's `Role` with a `RoleType` (`automatic` / `on-request` /
  `manual` / `opt-in`); use the concrete service (`AutomaticRoleService`, …), not
  the abstract base. `handleMembers()` re-syncs membership.
- **Affiliations** are polymorphic with an `AffiliationType` (`allowed` /
  `inverse` / `forbidden`, forbidden always wins); `RoleAffiliatedIdsService`
  resolves `(allowed ∪ inverse) ∖ forbidden`.
- **Permissions** — `CanUserService::check(...)` runs a pipeline over EVE entity
  IDs; `superuser` bypasses; permissions cached per user 5 min.
- **Compliance** — `IsUserCompliantService` checks every owned character has all
  required scopes; non-compliant memberships flip to `INACTIVE`.

## Testing
Needs PostgreSQL and Redis. The test DB is **`laravel_auth`** — a per-package name
so this suite runs in parallel with other packages' suites without collisions.
It's pinned with `force="true"` in `phpunit.xml` so an ambient `DB_DATABASE` (the
dev shell exports `seatplus`) can never redirect a `migrate:fresh` onto a real
database. **Tests must never touch `seatplus`.** Create it once:
`createdb laravel_auth`.

```bash
composer run test        # Pint + PHPStan + type-coverage + Pest
vendor/bin/pest --filter "test name"
```

`tests/Architecture/ArchitectureTest.php` asserts no `dd()`/`dump()` — keep it
passing.

> See the "Working in Orca" section of core's `CLAUDE.md` for the per-package
> test-DB rationale. Limit: two worktrees of *this* package share `laravel_auth`.

## Code style
Spatie PHP guidelines / PSR-12; new PHP files omit the license header (match the
newest sibling files).
