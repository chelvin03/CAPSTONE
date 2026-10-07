# Equipment requests, replies, and responses

The feature reuses `equipment`, `reservation_equipment`, the existing Laravel `notifications` table, the reservation reference, verified-email flow, SMTP mailer, audit logs, and role middleware. No separate chat system is introduced.

## Business behavior

- Requestor types remain `internal` and `external`. The public label is **Internal – MCST**. Only Internal requests can include inventory equipment or the existing additional-item text request.
- Changing the verified requestor type at final submission requires verifying the changed details again. This uses the existing email verification workflow; selecting Internal does not independently verify MCST affiliation.
- Internal requestors can request more than total inventory; this records demand and does not promise stock. Blank quantities mean no request. Nonblank quantities must be positive whole numbers.
- Admin reviews equipment separately from reservation approval. A reply can offer a full or partial quantity, or zero to explain unavailability. A positive offer may require requestor confirmation.
- Requestor acceptance sets **Accepted By Requestor**, records the response time, and notifies every admin through database notifications and the header's Equipment Updates menu. Declining sets **Declined By Requestor**. Neither action approves equipment or the reservation.
- Final equipment approval checks requested quantity, requestor confirmation when required, inventory status, and current schedule availability again. An accepted offer does not reserve stock or guarantee later approval.
- Availability is total stock minus peak simultaneous approved equipment allocation across approved/completed reservations on the date. Adjacent bookings do not overlap. Equipment inventory is shared across facilities. Waiting-list, rejected, cancelled, and pending reservations do not consume scheduled stock.
- All equipment review and relevant reservation approval/edit/reschedule transactions acquire inventory locks in the same order. Reservation approval and schedule changes recheck equipment allocation and roll back if stock would be overbooked.
- Offers, decisions, and responses have immutable notification snapshots and audit records. The pivot holds the current state. Existing `remarks` holds the admin reply; no duplicate reply column/table is introduced.
- Only admins can review, send official replies, or approve/reject equipment. Staff can view the equipment request and remarks through their existing read-only reservation details page.

## Tracking and email

Equipment updates are private to the browser session that submitted the reservation or opened its signed email link. A reference code alone cannot authorize an equipment response or reveal private messages. The signed link expires in seven days and grants access only to that reservation.

SMTP uses the existing configuration and runs after the database transaction commits. Failed email delivery leaves the reply and tracking notification intact, logs a safe diagnostic, and does not pretend the email succeeded. No live email delivery was performed during automated tests.

## Database installation

Run `php artisan migrate` on other installations. The added migration extends the existing pivot with `status`, `quantity_offered`, `reply_sent_at`, `requires_response`, `latest_offer_id`, `requestor_response`, and `requestor_responded_at`. Existing requested/approved quantities, remarks, keys, and timestamps are preserved.

The local project database migration has already been applied. The frontend assets were rebuilt using `npm run build`.

## Demonstration steps

1. As Admin, use existing Equipment management to add or identify available Chairs, Microphone, and Speaker inventory. For a partial-offer demo, set Chairs stock to 70 in a test database with no conflicting allocations.
2. Open the public reservation form, choose **Internal – MCST**, complete the existing verification, and request 100 Chairs. Submit a valid date/time, permit, attendance, and agreement. Save the reference.
3. Repeat with **External**. Equipment inputs should be hidden and disabled. Manually submitting equipment with External should return a validation error with no reservation/document stored.
4. Open the Internal reservation's Admin details. Confirm Chairs requested 100, available 70, approval Pending, and status Pending.
5. Open **Reply / Reject**, offer 70, enter the explanation, and check **Require requestor confirmation**. Click **Send Reply to Requestor**. Cancel the confirmation first; nothing should be sent. Repeat and choose **Send Reply**.
6. In the submission browser, track the reference, or open the secure link in the email. Confirm the message and quantity snapshot appear. Click **Accept 70 Chairs**. Confirm the state becomes Accepted By Requestor. In the Admin header/details, confirm the acceptance notification appears.
7. Admin clicks **Approve Equipment** for 70. Confirm equipment is Approved while the reservation's status remains unchanged. Approve the reservation through its existing approval button when appropriate.
8. Use another request to exercise **Decline Equipment Request**. Admin should receive a decline notice. Final approval must be blocked until a new positive offer is sent and accepted, when confirmation is required.
9. Test zero, negative, decimal, unknown equipment IDs, quantities above requested/available stock, maintenance inventory, and attempting to change Internal to External while an equipment request exists. Invalid writes must be rejected.
10. Log in as Staff. Equipment details and remarks should be visible; official review/approval/reply endpoints return HTTP 403. In a fresh browser without the signed link, equipment response endpoints also return HTTP 403.
11. Overlap stock test: with 150 Chairs, an approved allocation of 100 during 08:00–12:00 leaves 50 for an overlapping reservation. Attempting to approve 80 must fail; approving 50 succeeds. At 12:00 or another date, the 150 Chairs can be reused. The existing gym calendar already prevents conflicting gym bookings; demonstrate shared stock using separate facilities or run the automated overlap tests, rather than removing the calendar's conflict rule.
12. Change an approved reservation's date/time to overlap another stock allocation. The normal admin update/reschedule flow must reject stock overbooking and preserve the previous schedule. An existing authorized force reassignment still releases displaced reservations' equipment by moving them to waiting-list/cancelled status.

Automated feature tests: `php artisan test --compact tests/Feature/EquipmentRequestTest.php`.

## Files created for this feature

- `app/Http/Controllers/EquipmentRequestController.php`
- `app/Services/EquipmentRequestService.php`
- `app/Notifications/EquipmentUpdate.php`
- `app/Mail/EquipmentRequestUpdated.php`
- `database/migrations/2026_10_07_010000_add_equipment_request_review_fields.php`
- `resources/views/admin/reservations/partials/equipment-review.blade.php`
- `resources/views/public/reservations/partials/equipment-updates.blade.php`
- `resources/views/emails/equipment-request-updated.blade.php`
- `resources/views/components/equipment-notifications.blade.php`
- `tests/Feature/EquipmentRequestTest.php`
- `docs/equipment-request-review.md`

## Files modified for this feature

- `app/Http/Controllers/PublicReservationController.php`
- `app/Http/Controllers/Admin/ReservationController.php`
- `app/Http/Controllers/Staff/ReservationController.php`
- `app/Models/Reservation.php`
- `app/Models/Equipment.php`
- `routes/web.php`
- `resources/views/public/reservations/create.blade.php`
- `resources/views/public/reservations/partials/tracking.blade.php`
- `resources/views/public/reservations/track.blade.php`
- `resources/views/admin/reservations/_form.blade.php`
- `resources/views/admin/reservations/create.blade.php`
- `resources/views/admin/reservations/edit.blade.php`
- `resources/views/admin/reservations/show.blade.php`
- `resources/views/staff/reservation/show.blade.php`
- `resources/views/layouts/app.blade.php`
- `tests/Feature/PublicReservationPermitTest.php`
- `tests/Feature/PublicVerificationTest.php`
- `tests/Feature/FileMaintenanceTest.php`
- `tests/Feature/PublicCalendarTest.php` (fixture compatibility with previously added type/agreement requirements)

Generated assets: `public/build/manifest.json` and Vite CSS/assets were rebuilt. Other existing working-tree changes from the agreement/capacity/type tasks were preserved; BI and calendar implementation files were not modified for this feature.
