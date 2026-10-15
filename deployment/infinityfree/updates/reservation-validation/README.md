# Reservation validation workflow

Prepared locally; not deployed to the hosted site.

Upload the four files under htdocs to their matching paths in the existing Laravel application. Back up the matching live files first. These are complete files from the current local application; compare and merge if the live site has different customizations. Preserve .env, database, uploads, vendor and APP_KEY.

If routes or views are cached, run `php artisan route:clear` and `php artisan view:clear` after uploading. No database migration or asset build is needed. Validation uses the existing reservation_status_histories table, including changed_by and created_at.

Admin > Reservations > View: review details and required documents, then click Validate Reservation. New, Pending, Pencil and Waiting list requests may be validated. After validation the Approve / For AR action becomes available. Approval endpoints also require validated status. Priority overrides still reassign conflicting requests, but the new priority request requires validation and approval. Existing approved records are unchanged. Staff cannot validate.

The details page displays who validated and when. Public tracking displays Validated. Pencil confirmations still recheck schedule availability. The status filter applies immediately on selection.

Validation: 42 focused tests passed, 335 assertions, covering validation history and permissions, approval gating, pencil conflicts, receipts, public tracking, equipment checks and priority reservations.
