# Architecture baseline

## 1. Purpose

This repository is the implementation baseline for the Afrik Appart short-term rental website. The first milestone establishes the Laravel 12 application skeleton, default environment configuration, a minimal public landing page, and a health endpoint suited to local validation and future Bluehost deployment.

## 2. Runtime and hosting assumptions

- PHP 8.3 runtime target for production deployment
- Laravel 12 on a standard shared-hosting layout
- MySQL 8.x through InnoDB and utf8mb4
- Vite for local asset compilation and production static asset build
- Bluehost-safe deployment with private application files and `.env` outside `public_html`

## 3. Current implementation status

M01 covers the baseline architecture only. It does not yet include:

- database schema or migrations beyond the Laravel defaults
- user authentication and authorization
- reservations or booking flows
- payment adapters or live external integrations
- production media import or external calendar synchronization

## 4. Design direction

The sample HTML prototypes informed the product direction, including:

- cream and warm neutral backgrounds
- emerald and gold brand accents
- large rounded surfaces and premium card layouts
- photographic hero treatment and responsive search panel

The first Laravel page uses a simplified version of that direction without copying static prototype copy or unsupported metrics.

## 5. Local development commands

```bash
cd repository
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
php artisan serve
```

## 6. Health endpoint

The application exposes a safe, public health endpoint at `/health` that returns basic process metadata without revealing secrets, credentials, or internal configuration.

## 7. Deployment principle for Bluehost

The deployment model assumes that the application path and `.env` live outside the document root while the web root only exposes public assets and persistent uploads. This keeps private configuration and application code unreachable from the public web server.
