# Mobile reservation calendar

Prepared locally; not uploaded. Upload only htdocs/public/css/reservation-wizard.css to the matching online htdocs/public/css folder, replacing the old file after downloading a backup.

The calendar fits all seven columns on phones and tablets. Weekday headings display the first three letters, dates have compact 44px-high buttons, month controls fit the available width and time slots use one column. Existing date selection and availability logic are unchanged. The CSS preserves the current local compact reservation layout.

Verified in local headless Chrome at 320, 360, 390, 430, 768 and 1280 CSS pixels using the calendar markup. All seven columns fit with no page or calendar horizontal overflow. This is a layout fixture check, not an online booking test.

No SQL, migration, controller, JavaScript or asset build is required. The existing reservation view versions the CSS URL by the file modification time. Refresh or reopen the mobile page after upload; then confirm dates and month navigation work on the online site.
