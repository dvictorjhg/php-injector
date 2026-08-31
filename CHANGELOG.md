# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project follows Semantic Versioning.

## [1.0.1] - 2026-08-27

### Added

- Regression coverage for parent provider inheritance and explicit arguments overriding `#[Inject]` attributes.
- A rebuilt static documentation experience with English and Spanish translations, theme switching, and expanded guide sections.

### Changed

- Refined parameter and provider resolution, including explicit argument precedence and parent-provider alias handling.
- Lowered the minimum supported PHP version to 8.3, matching the syntax used by the package and test suite.
- Updated usage, resolution, and contributor guidance.

## [1.0.0] - 2026-08-26

### Added

- Initial public package surface for PHPInjector.
- GitHub issue templates for bug reports and feature requests.
- A pull request template for structured reviews.
- A GitHub Actions CI workflow that validates Composer metadata, runs PHPStan and PHPUnit with coverage on PHP 8.4 and 8.5, uploads Clover reports to Codecov, and enforces the coverage threshold on push and pull request.
- PHPStan static analysis configuration at `level=max`.
- A Composer `analyse` script and CI step for PHPStan.
- Reflection-based dependency injection for classes, methods, closures, invokable objects, global functions, and callable strings.
- A PSR-11 compatible container implementation.
- `#[Inject]` attribute support for named value resolution.
- Singleton provider support through `PHPInjector\Contracts\Singleton`.
- Implicit singleton caching for automatically constructed classes, with a `#[Transient]` class-level opt-out for fresh resolution.
- Provider composition through parent injectors.
- Type-key provider resolution for named classes, interfaces, abstract classes, enum instances, and `Closure` values.
- Variadic parameter resolution with zero-or-more values, positional expansion, and list-valued providers.
- Explicit-only handling for union, intersection, and DNF parameter types to avoid ambiguous provider selection.
- Active injector staircase with static `Injector::inject()` and `PHPInjector\DI\inject()` entry points.
- Initial repository documentation covering usage, contribution, security, attribution, and license information.
- A standalone static landing and documentation page covering the guide, lifecycle, resolution order, and public API.
