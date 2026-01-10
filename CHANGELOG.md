# Changelog

All notable changes to the Spatial CLI package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-01-10

### Added

- **Optional Flags**: All 6 generators now support optional `--logging` and `--tracing` flags for clean code by default
- **Configuration File Support**: Project-wide generator defaults via `.spatial.yml`
  - Global defaults for all generators
  - Command-specific overrides
  - Priority system (CLI > overrides >defaults > hardcoded)
- **Dry-Run Mode**: Preview generated code without creating files using `--dry-run` flag
- **Smart Error Messages**: Contextual error handling with typo detection and helpful suggestions
- **AbstractGenerator Base Class**: Eliminated code duplication across generators
- **EntityManager Lifecycle**: Added `getEntityManager()` and `releaseEntityManager()` to `spatial-psr7`
- New generator-specific flags:
  - `--auth` for controllers (adds authorization attributes)
  - `--retry=<n>` for jobs (custom retry count)
  - `--releaseEntity` for query/command handlers

### Changed

- Generators now produce clean code by default (no forced OTEL dependencies)
- Improved error messages with module suggestions and typo detection
- Updated README with comprehensive documentation

### Fixed

- Critical bug where `releaseEntityManager()` method didn't exist
- EntityManager resource leaks in long-running processes

## [1.0.0] - 2025-12-14

### Added

- Initial release of Spatial CLI package
- Code generators for CQRS patterns (query, command)
- Event listener generator
- Controller, middleware, and job generators
- Database migration tools
- Code quality tools (lint, analyze)

[1.1.0]: https://github.com/kodesafi/spatial-cli/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/kodesafi/spatial-cli/releases/tag/v1.0.0
