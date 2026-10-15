# Admin pencil bookings

Upload the files under htdocs to matching paths in the live Laravel application. Back up matching online files first. This package includes the controller, matching admin views and their view partials, GymCapacity and Facility dependencies. No new database column, migration, route, or asset build is required: reservations.status is already a string.

Upload dependencies and views before the controller. Preserve the live .env, APP_KEY, database, uploads and vendor folder. If online files contain newer custom changes, merge rather than overwrite those files. This package is prepared locally and has not been deployed.

Use Admin > Reservations > Pencil Booking, complete the reservation details and save. The saved reservation has pencil status and holds the date/time in availability and conflict checks. Open its details to Confirm Pencil Booking (approved) or Cancel Reservation (requires a reason). Holds have no automatic expiry and are excluded from pending auto-cancellation. No priority override is allowed when creating a pencil hold. Confirmation rechecks reservations, maintenance blocks and facility availability.

Validation: five focused tests passed (35 assertions), covering creation, occupied slots, confirmation, cancellation and slot release, maintenance blocks, staff rejection and pending timeout exclusion.
