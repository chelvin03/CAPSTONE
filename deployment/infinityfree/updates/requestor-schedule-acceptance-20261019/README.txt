Upload these files to their matching /htdocs paths in UPLOAD-FILE-LIST order (controller last).
Requires the previously uploaded schedule-proposal database tables, event-types config and shared event-type field. No SQL change is required for this patch.
Ordinary reschedules always create an email proposal; the original booking stays unchanged until the requestor accepts.
Unchanged schedules are blocked in preview and save. Same-day time changes remain valid.
Waiting-list approval offers may retain the requested date/time; they still require eligibility review and fresh availability checks.
Force assign is a separate direct admin action requiring a confirmed preview; it does not request acceptance.
Existing notification emails cannot be changed. Test with a newly sent proposal.
After upload, hard-refresh the reservation page. If old views remain, clear only generated PHP files in storage/framework/views, keeping the folder and .gitignore.
Test: unchanged schedule blocked; ordinary proposal with old send_proposal=0 cannot bypass acceptance; original remains unchanged; Review New Schedule email link; accept/decline; Book Again after decline; force assignment preview and cancellation/wait-list notices.

Latest policy: 14 event types with no numbers or Other category. Minimum duration is now 2 hours in validation, proposals, rescheduling and the public calendar. Existing records are not rewritten. Upload every file in the refreshed UPLOAD-FILE-LIST.txt.
