# ბათუმის სახლში — რეზერვაციის სისტემა

Georgian, responsive reservation application built with React/Vinext and Cloudflare D1. Public guest flow: date/time and party size → 2D table → optional menu preselection → guest details → confirmation. Admin supports reservations, visit statuses, guest history, menu and floor-plan editing.

## Runtime
This is a Cloudflare Worker application, not Laravel/PHP. The Sites build pipeline provisions D1 and applies the generated Drizzle migrations. GitHub alone does not host the backend. Run the bundled installation and build scripts; Node 22.13+ required. Preserve the lockfile.

## Setup before accepting guests
1. Configure `ADMIN_EMAILS` with explicit comma-separated administrator ChatGPT account emails in runtime environment settings. Admin routes fail closed when unset and require ChatGPT sign-in plus server-side allowlist membership. Never grant admin to all signed-in users.
2. Add the restaurant's real tables, capacity and 2D positions in `/admin` → დარბაზი. Catalog starts empty intentionally: no invented tables are offered for actual bookings.
3. Add real dishes and prices in `/admin` → მენიუ. Prices are stored as integer tetri and snapshotted server-side at booking.
4. Confirm operational hours (currently 12:00–22:00 arrival), 120-minute reservation duration, 30-minute increments, and 90-day booking horizon in Asia/Tbilisi.
5. Replace the concept image with approved restaurant photography. The current atmosphere image is a design concept.
6. Private review deployment is owner-only. Public customer access requires deliberately changing deployment audience; admin remains separately allowlisted.

## Data and safeguards
Names, surname, phone, birthday day/month (no year), optional notes, and separate unticked marketing consent are stored with each booking. There is no payment processing or SMS delivery. Confirmation is shown immediately and can be printed. Birthdays are validated using leap year 2000. Marketing consent is timestamped. Guests are grouped by normalized phone in the admin, but this does not verify phone ownership and should not be used as authentication. No private booking lookup is exposed publicly.

A D1 atomic batch inserts the booking and four half-hour occupancy slots. A unique (table_id, date, minute) index prevents overlap across concurrent reservations. Cancellation/no-show releases slots atomically. Booking IDs provide retry idempotency. Completed/cancelled/no-show reservations cannot be restored, preventing silent collision. API responses use no-store; admin refreshes every 15 seconds, availability every 20 seconds. Menu selection is optional. Admin lists the latest 1000 reservations; pagination is a future scaling task.

## Verification
`npm run db:generate`, `npm run build`, and `python3 tests/reservation_integrity.py`. Integrity checks apply the actual migration SQL and exercise conflicting reservations, rollback, cancellation and rebooking.
