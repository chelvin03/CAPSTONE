# Admin schedule calendar

Prepared locally; nothing uploaded. Back up matching online files. Upload public/css/admin-schedule.css, app/Services/AdminScheduleService.php and app/Models/Facility.php before uploading app/Http/Controllers/Admin/ScheduleController.php and resources/views/admin/schedule/index.blade.php to matching paths under online htdocs. Create missing folders and files. Preserve the online .env, APP_KEY, database and uploads. No database migration or asset build is required.

This uses the existing admin.schedule.index and admin.schedule.blocks.store/destroy routes and schedule_blocks table. Green dates have available intervals, including partially held dates with remaining free time; red dates have no free operating time because of bookings or holds; closed and past dates are gray. Click a date for exact time intervals. Pending and pencil bookings are holds, not approved reservations. Booking details retain that distinction. Filters never hide occupancy from calendar calculations.

Verified: 15 existing schedule tests (87 assertions) and the added calendar color/pencil test (7 assertions) pass locally.
