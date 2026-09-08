# ბათუმის სახლში — Laravel Reservation Platform

Production-ready Laravel 13 foundation for restaurant reservations.

## Included
- 4-step guest reservation flow
- Real-time 2D table availability
- 2-hour slot protection against double booking
- Menu pre-order with price snapshots
- Guest name, surname, phone and birthday database
- Optional marketing consent
- Reservation confirmation code
- Admin login, reservation statuses and guest CRM
- Menu management
- 2D table / floor management
- Responsive desktop and mobile UI
- Health endpoint at `/up`

## Laravel Cloud
1. Connect `tripalltime2026/Saklshi-` to Laravel Cloud.
2. Application directory: repository root (`/`).
3. Provision PostgreSQL or MySQL. Laravel Cloud can inject the resource credentials automatically.
4. Set `APP_KEY`, `ADMIN_EMAIL` and `ADMIN_PASSWORD` in environment variables.
5. Run:
   - `php artisan migrate --force`
   - `php artisan db:seed --force`
6. Open `/` for reservations and `/admin` for management.

For a fresh Laravel app key use `php artisan key:generate --show`.
