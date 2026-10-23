# Security Policy

## Supported Versions

Security fixes are provided for the latest release line and the current default branch.

## Runtime And Trust Boundaries

Use PHP 8.3.36+, 8.4.27+, or 8.5.12+ and keep the runtime patched. These
minimums include the PHP security releases published on 2026-10-21. CI runs
`composer audit` to check installed dependencies for known advisories.

Injection targets, class names, callable strings, and provider registrations
must come from trusted application code, never directly from request input.
The injector can invoke public functions and methods; it is not a sandbox.

The active injector and its cached providers persist for the lifetime of the
process. Do not store credentials, authenticated users, or other request-specific
state in shared providers in long-running workers.

## Reporting A Vulnerability

Please do not open a public issue for security vulnerabilities.

Instead, report the issue privately to [vihoga@hotmail.es](mailto:vihoga@hotmail.es) with:

- A clear description of the problem
- Steps to reproduce it
- Impact assessment if known
- Any suggested remediation or patch

The maintainer will acknowledge receipt as soon as possible and work toward a fix
before public disclosure.
