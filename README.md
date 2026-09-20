# ბათუმის სახლში — Laravel Reservation Platform

Laravel 13 restaurant reservations by **guest count**, with menu pre-orders and an operational admin dashboard.

## Included
- Date/time, guest count, occasion and contact information; no table selection or automatic table assignment
- Capacity checks across overlapping visits, with transaction locks and server-side revalidation
- Admin capacity, maximum group size, visit duration, buffer and booking pause controls
- Reservation status history and settings audit trail
- Menu categories, search, pagination and price snapshots
- Guest CRM, live admin updates and complete operational exports
- Responsive web interface; health endpoint `/up`

The full PDF specification includes accounts, branches, payment, loyalty, notifications and native mobile apps. These are **not implemented** by this capacity change. See [the detailed Georgian specification and delivery stages](docs/reservation-loyalty-spec.ka.md).

## Deploy
1. Connect `tripalltime2026/Saklshi-` to Laravel Cloud; application directory `/`.
2. Configure PostgreSQL/MySQL and set `APP_KEY`, `ADMIN_LOGIN`, `ADMIN_PASSWORD`.
3. Back up the database, then run `php artisan migrate --force`.
4. Open `/admin#capacity` and verify capacity. Existing installations import the sum of active table seats once. New empty installations start with zero seats until configured. Do not assume the previous demo table total is the actual restaurant capacity.
5. `php artisan db:seed --force` no longer creates demo tables or overwrites capacity. Existing menu data is preserved.
6. Start accepting bookings. This release does not charge a deposit or send SMS.

Existing reservations retain their historical table IDs. New reservations have a nullable table ID and a stored capacity end time including buffer. The capacity migration deliberately refuses rollback: older application code requires tables for every booking. Use a forward fix, or restore the pre-deploy backup together with the matching old code during controlled maintenance.

## Verification
`APP_ENV=testing php tests/operations.php` uses an in-memory SQLite database. CI also runs the browser booking/admin/export flow. `tests/prepare-browser.php` sets a test-only capacity and must only run in the testing environment. Validate parallel capacity writes on the production database engine before deployment.
