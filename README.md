# seatplus/auth

[![CI](https://github.com/seatplus/auth/actions/workflows/laravel.yml/badge.svg)](https://github.com/seatplus/auth/actions/workflows/laravel.yml)

Handles authentication, authorisation, and SSO scope compliance for the seatplus EVE Online management platform. This is the core package — `seatplus/eveapi` and `seatplus/web` both depend on it.

## Overview

### Role system

Four role types with distinct membership and permission semantics:

| Type | Membership | Use case |
|------|-----------|---------|
| `automatic` | Auto-assigned when a character belongs to a configured corporation or alliance | Fleet / alliance access |
| `on-request` | User applies, moderator approves or denies | Corp-specific elevated access |
| `manual` | Admin explicitly adds / removes individual users | One-off grants |
| `opt-in` | User self-joins if they meet the criteria | Opt-in programmes |

### Affiliation system

Every role has `Affiliation` records that define **permission scope** (which EVE entities the role holder can access data for), not membership. Three types:

- `allowed` — these corporations / alliances / characters are in scope
- `inverse` — everyone *except* these is in scope
- `forbidden` — always excluded, overrides `allowed` / `inverse`

### SSO scope compliance

`IsUserCompliantService` checks whether every character owned by a user has all required OAuth scopes. Required scopes are aggregated from global settings, corporation-level `SsoScopes` records, and alliance-level records. Non-compliant users have their role memberships set to `inactive` automatically on the next `handleMembers()` call.

### Permission checking

`CanUserService::check()` runs a Laravel Pipeline to validate a set of EVE entity IDs against a user's permissions. The pipeline strips IDs the user owns, IDs covered by in-game corporation roles (e.g. Director), and IDs covered by Spatie permissions. Any remaining IDs are denied. The `superuser` permission bypasses all checks.

## Installation

```bash
composer require seatplus/auth
```

Publish and run migrations:

```bash
php artisan vendor:publish --provider="Seatplus\Auth\AuthServiceProvider"
php artisan migrate
```

## Usage

### Add OAuth scopes to a character

By default the minimal scopes are requested. To step up a character to additional scopes, redirect to:

```
/eve/sso/{character_id}/step_up?add_scopes=esi-skills.read_skills.v1,esi-wallet.read_character_wallet.v1
```

### Check permissions

```php
use Seatplus\Auth\Services\Dtos\ValidateIdsDTO;
use Seatplus\Auth\Services\CanUserService;

$dto = ValidateIdsDTO::make(entity_ids: [12345678], user: $user);
CanUserService::check($user, $dto, permissions: ['view member tracking']);
```

## Development

### Requirements

- PHP 8.3+
- PostgreSQL (user `seatplus`, password `secret`, database `laravel` @ `127.0.0.1:5432`)
- Redis @ `127.0.0.1:6379`

### Running the test suite

```bash
composer run test           # lint + PHPStan + type-coverage + unit tests
composer run test:unit      # unit tests only
composer run test:lint      # Pint formatting check
composer run lint           # auto-fix formatting with Pint
composer run test:types     # PHPStan static analysis
composer run test:type-coverage  # 100% type coverage check
```

