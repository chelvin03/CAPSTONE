# Public hours to midnight and monthly dashboard summary

Prepared locally. Nothing uploaded and no real email sent. Nine calendar/midnight/summary tests passed (80 assertions).

Back up matching online files. Upload all nine htdocs files to the same paths. Upload service changes before the controller and views. No migration, SQL, npm build, routes or vendor upload is needed. Preserve the online .env and APP_KEY.

The package changes the default gym hours to 08:00–24:00 and teaches public validation, calendar availability, admin schedule hours and utilization to handle the midnight end boundary. After uploading, edit the existing online .env to add or update:

```dotenv
GYM_OPENING_TIME=08:00
GYM_CLOSING_TIME=24:00
```

If bootstrap/cache/config.php exists online, back it up and remove that generated configuration cache file. Preserve packages.php/services.php and other private files.

The public form keeps flexible minute-based start/end times. Select End at 12:00 midnight to end at the midnight after the selected reservation date. Internal value 24:00 preserves same-date overlap logic; 00:00 is not silently treated as a next-day end by the server. Arbitrary overnight/multiday bookings and times past midnight are rejected.

The dashboard's existing trends partial now begins with View Monthly Summary for the reporting year. Expand it to see all twelve months, total reservations, approved count/percentage, disapproved count/percentage and other statuses. Counts use scheduled reservation month. Approved includes completed; disapproved means rejected. Cancelled/pending/pencil/waiting-list records are Other. Percentages use all records in that month as the denominator, so the two percentages need not sum to 100. Empty months display zero. The monthly table ignores day/month/status narrowing to provide a full-year outcomes comparison, while existing event/requestor/facility filters apply.

Existing dashboard-reference admin.blade.php already includes dashboard.partials.trends, so no main dashboard replacement is needed. Existing optional equipment and public plain-calendar changes are preserved.
