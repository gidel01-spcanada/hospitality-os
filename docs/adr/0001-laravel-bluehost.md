# ADR 0001: choose Laravel 12 with a Bluehost-safe application/public split

## Status

Accepted for M01.

## Context

The project requires a production-ready e-commerce style rental application with modern authentication, database-driven templates, and deployment to Bluehost shared hosting using SFTP. The design direction is premium and responsive, and the code must remain maintainable while remaining compatible with a shared-host policy.

## Decision

The project will use Laravel 12 with PHP 8.3-compatible code, MySQL 8.x, Blade templates, and a minimal Vite build for frontend asset compilation. The application code and private config will live outside the public web root, while only the compiled public files and persistent uploads remain publicly served.

## Consequences

- The framework provides strong defaults for routing, security, storage, and UI templating.
- The BlueHost deployment pattern remains secure and operationally clear.
- The app remains compatible with local development and later production migration.
- Additional configuration is still required for real payment providers, SMTP, and hosting-specific secrets.
