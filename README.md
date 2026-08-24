# Afrik Appart

This repository contains the Laravel 12 baseline for the Afrik Appart short-term rental website.

## Local development

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
php artisan serve
```

## Validation

- `php artisan route:list`
- `php artisan test`
- `curl http://127.0.0.1:8000/health`

## Project status

This is the M01 architecture baseline only. Database models, booking flows, payments, and media processing will be added in later milestones.
