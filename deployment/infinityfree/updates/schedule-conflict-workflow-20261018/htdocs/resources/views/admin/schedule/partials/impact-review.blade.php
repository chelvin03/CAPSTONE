<input type="hidden" name="impact_token" x-ref="impactToken">
<p class="schedule-impact-error block-form-wide sm:col-span-2" role="alert" x-show="impactError" x-text="impactError"></p>
<dialog x-ref="impactDialog" class="schedule-impact-dialog" @cancel="impactPreview = null">
    <h2>Review affected bookings before applying</h2>
    <p x-text="impactPreview?.summary"></p>
    <p><strong>Reason:</strong> <span x-text="impactPreview?.reason"></span></p>
    <p class="schedule-impact-error" x-show="impactPreview?.can_apply === false" x-text="impactPreview?.is_proposal ? 'This proposed slot is unavailable. Choose an available alternative. Proposals cannot force assign or displace other bookings.' : 'This change cannot be applied yet. Choose an available slot, release any administrator block, or enable force assignment with a conflict resolution. Completed bookings cannot be displaced.'"></p>
    <template x-for="row in (impactPreview?.rows || [])" :key="row.reference">
        <article class="schedule-impact-row">
            <a :href="row.url" target="_blank" rel="noopener" x-text="row.reference"></a>
            <p><span x-text="row.date"></span> · <span x-text="row.time"></span> · <span x-text="row.status"></span></p>
            <p class="outcome" x-text="row.outcome"></p>
            <p>Email notice: <span x-text="row.email"></span></p>
        </article>
    </template>
    <p x-show="!impactPreview?.rows?.length">No existing bookings are affected.</p>
    <section x-show="impactPreview?.alternatives?.length"><h3>Available alternatives on the selected date</h3><p>Each option fits the full duration and cleaning separation without displacing a confirmed booking. Equipment allocations are rechecked when saving.</p><template x-for="choice in (impactPreview?.alternatives || [])" :key="choice.start"><button type="button" class="schedule-impact-row" @click="useImpactAlternative(choice)" x-text="'Use ' + choice.label"></button></template></section>
    <p class="schedule-impact-note">Nothing changes until you confirm. The server rechecks this preview when saving. Emails are attempted after a successful save; missing email addresses require manual contact.</p>
    <div class="schedule-impact-actions"><button type="button" @click="$refs.impactDialog.close(); impactPreview = null">Back / change details</button><button type="button" class="apply" :disabled="impactBusy || impactPreview?.can_apply === false" @click="confirmImpact" x-text="impactPreview?.is_proposal ? 'Confirm and send proposal' : 'Confirm and apply changes'">Confirm and apply changes</button></div>
</dialog>
