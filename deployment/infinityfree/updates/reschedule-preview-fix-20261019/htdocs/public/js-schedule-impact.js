function scheduleImpactReview(previewUrl) {
    return {
        impactBusy: false, impactError: '', impactPreview: null, impactForm: null,
        async reviewImpact(event) {
            if (this.impactBusy) return;
            this.impactError = '';
            const form = event.currentTarget;
            if (!form.reportValidity()) return;
            if (this.durationError || this.error) {
                this.impactError = this.durationError || this.error; return;
            }
            this.impactForm = form;
            this.impactBusy = true;
            try {
                const previewData = new FormData(form);
                previewData.delete('_method');
                const response = await fetch(previewUrl, {method: 'POST', credentials: 'same-origin',
                    headers: {'Accept': 'application/json'}, body: previewData});
                const data = await response.json();
                if (!response.ok) throw new Error(response.status === 419 ? 'Your session changed. Refresh the page before continuing.'
                    : Object.values(data.errors || {}).flat().join(' ') || data.message || 'Unable to review the schedule.');
                this.impactPreview = data;
                this.$refs.impactDialog.showModal();
            } catch (error) { this.impactError = error.message || 'Could not load the preview. Please try again.'; }
            finally { this.impactBusy = false; }
        },
        confirmImpact() {
            if (!this.impactPreview?.token || this.impactPreview.can_apply === false || this.impactBusy) return;
            if (!this.impactForm.reportValidity()) return;
            this.$refs.impactToken.value = this.impactPreview.token;
            this.impactBusy = true;
            HTMLFormElement.prototype.submit.call(this.impactForm);
        },
        useImpactAlternative(choice) {
            this.impactForm.elements.namedItem('reservation_date').value = choice.date;
            this.start = choice.start;
            this.end = choice.end;
            if (typeof this.updateEnd === 'function') this.updateEnd();
            this.impactForm.elements.namedItem('force_assign').checked = false;
            this.$refs.impactToken.value = '';
            this.$refs.impactDialog.close();
            this.impactPreview = null;
            this.impactError = 'Alternative selected. Review the new schedule before applying.';
        }
    };
}
