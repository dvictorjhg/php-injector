# Contributing

Thank you for contributing to PHPInjector.

The examples below follow the usual contributor flow: prepare a local checkout, verify the package, describe the change clearly, and provide a reproducible report when something breaks.

## Before You Start

- Open an issue first for breaking changes, new public APIs, or major behavioral changes.
- Keep pull requests focused. Smaller changes are easier to review and safer to release.
- Make sure your change fits the package goal: lightweight, reflection-based dependency injection for modern PHP.

## Local Workflow

### Example #1 Install dependencies and run static analysis

```bash
composer install
composer analyse
```

The final lines should be similar to:

```text
Generating autoload files
[OK] No errors
```

`composer install` output will vary by platform and dependency state. The important part is that `composer analyse` finishes with `No errors` before you continue.

### Example #2 Run the unit test suite before opening a pull request

```bash
composer test
```

The above example will output:

```text
PHPUnit 11.x by Sebastian Bergmann and contributors.

OK (109 tests, 219 assertions)
```

If you switch branches, move files, or see repeated `Class ... not found` errors during the test run, refresh Composer autoload files and rerun the suite:

```bash
composer dump-autoload
composer test
```

## Development Expectations

- Match the existing coding style and keep `declare(strict_types=1);` where the project already uses it.
- Add or update PHPUnit tests for any behavioral change.
- Update documentation when public behavior, examples, or package metadata changes.
- Avoid unrelated refactors in the same pull request.

## Pull Requests

### Example #3 Describe a focused pull request

````markdown
## Summary

Fix scalar argument resolution for callable strings.

## Behavior Change

Before this change, calling `Injector::inject('strlen', ['string' => 'cache'])`
could fail when the callable path was not documented clearly enough in tests.

After this change, callable-string examples and tests cover the supported
argument shape explicitly.

## Verification

- composer validate --strict
- composer analyse
- composer test
- composer test:coverage
- composer coverage:check
````

The above example gives reviewers the problem, the behavior change, and the verification steps in one place.

- Keep commit history readable before requesting review.
- Include test coverage for fixes and new features.
- Note any compatibility implications, especially around constructor resolution, attribute handling, callable injection, or container behavior.
- Use the pull request template and fill in the verification section before requesting review.

## Reporting Bugs

### Example #4 Report a bug with a minimal reproducer

````markdown
## Environment

- PHP: 8.4.x or 8.5.x
- Package: 1.0.0, dev-main, or commit SHA

## Reproducer

```php
<?php

declare(strict_types=1);

use PHPInjector\DI\Injector;

$injector = new Injector();

var_dump(Injector::inject('strlen', ['string' => 'cache']));
```

## Expected Behavior

The injector returns `int(5)`.

## Actual Behavior

The injector throws an exception.
````

The above example gives maintainers enough detail to reproduce the problem quickly.

When opening an issue, include:

- PHP version
- Package version or commit
- Minimal reproducible example
- Expected behavior
- Actual behavior

The repository includes GitHub issue forms for bug reports and feature requests. Use them when possible so the required details are captured consistently.

## License

By submitting a contribution, you agree that your work will be licensed under Apache-2.0 with the repository notices preserved where applicable.
