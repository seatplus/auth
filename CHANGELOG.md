# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [5.0.0] - 2026-07-30

Upgrading to this version requires updating your `auth` package to `^5.0.0`.

You must implement the `auth.login` and `auth.logout` routes in your application — the `LoginController` and `LogoutController` have been removed from the package.

Affiliation and permission resolution has been reworked: authorization now resolves affiliated entity
IDs live in set-based SQL via `AffiliationResolver` rather than caching a materialised per-permission id
array. If you referenced the removed services below, migrate to `AffiliationResolver::coveredIds()` (a
bounded membership test) or its `*IdsSubquery()` façades.

### Added
- `AffiliationResolver` — set-based affiliation resolution with two façades: `coveredIds()` (bounded predicate) and `characterIdsSubquery()` / `corporationIdsSubquery()` / `allianceIdsSubquery()`.
- Event-based invalidation of the cached permission object on role and affiliation changes.
- Composite index on `affiliations(role_id, affiliatable_type, affiliatable_id)`.
- Introduced LoginAssetAction to serve login assets
- Introduced LogoutAction to serve logout assets

### Changed
- `CanUserService::check()` resolves affiliations live, so a changed role, a newly-added corporation, or membership churn is reflected on the next request (previously up to ~5 minutes stale).
- The cached `user_permissions_{id}` object no longer contains the `permissions` (permission → affiliated-id array) slice; it now carries `permission_roles` (permission → role ids) plus the identity slice.
- Switching main character has changed to use `PUT: auth/main-character/switch/{new_character_id}`. The correct route parameters are now required.

### Removed
- **Removed `RoleAffiliatedIdsService`, `RolePermissionObjectService`, and `AffiliationResolver::resolve()`** — the old materialised affiliated-id path. Use `AffiliationResolver::coveredIds()` or the `*IdsSubquery()` façades instead.
- Removed login controller and route
- Removed logout controller and route

## [4.0.0] - 2024-09-01
### Added
