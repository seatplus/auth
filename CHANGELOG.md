# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Upgrading to this version will require you to update your `auth` package to `^4.0.0`.
You are required to implement the `auth.login` and `auth.login` routes in your application. The `LoginController` and `LogoutController` have been removed from the package.


### Added
- Introduced LoginAssetAction to serve login assets
- Introduced LogoutAction to serve logout assets

### Changed
- Switching main character has changed to use `PUT: auth/main-character/switch/{new_character_id}`. The correct route parameters are now required.

### Fixed

### Removed
- Removed login controller and route
- Removed logout controller and route

## [4.0.0] - 2024-09-01
### Added
