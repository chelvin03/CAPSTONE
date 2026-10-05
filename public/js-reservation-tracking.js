function reservationTracker(url) {
    return {
        version: '', timer: null, busy: false, stopped: false,
        refreshMessage: 'Status updates automatically every 5 seconds.',
        init() { this.refresh(); this.timer = setInterval(() => this.refresh(), 5000); },
        destroy() { this.stopped = true; clearInterval(this.timer); },
        async refresh() {
            if (this.busy || this.stopped || document.hidden) return;
            this.busy = true;
            try {
                const response = await fetch(url, {headers: {'Accept': 'application/json'}, cache: 'no-store'});
                if (!response.ok) throw Error('Unable to refresh');
                const data = await response.json();
                if (this.stopped) return;
                if (this.version !== data.version) { this.$refs.trackingPanel.innerHTML = data.html; this.version = data.version; }
                this.refreshMessage = 'Status updates automatically every 5 seconds. Last checked: ' + new Date().toLocaleTimeString();
            } catch (error) { this.refreshMessage = 'Updates are temporarily unavailable. Retrying automatically; you can also refresh this page.'; }
            finally { this.busy = false; }
        }
    };
}
