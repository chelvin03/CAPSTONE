function adminSchedule(days, selected, tableDates, view = 'month', hours = null) {
    return {
        days, selected, tableDates, view, hours, loadedMonth: selected.slice(0, 7), loading: false, showMonthTable: false,
        get day() { return this.days[this.selected]; },
        clock(seconds) {
            const hour = Math.floor(seconds / 3600) % 24;
            const minute = Math.floor(seconds % 3600 / 60);
            return `${hour % 12 || 12}:${String(minute).padStart(2, '0')} ${hour < 12 ? 'AM' : 'PM'}`;
        },
        get hourRows() {
            if (!this.hours) return [];
            const rows = [];
            for (let start = this.hours.start; start < this.hours.end; start += 3600) {
                const end = Math.min(start + 3600, this.hours.end);
                rows.push({ start, end, label: `${this.clock(start)} – ${this.clock(end)}` });
            }
            return rows;
        },
        get weekDays() {
            const monday = new Date(this.selected + 'T00:00:00Z');
            monday.setUTCDate(monday.getUTCDate() - (monday.getUTCDay() + 6) % 7);
            return Array.from({length: 7}, (_, index) => {
                const date = new Date(monday);
                date.setUTCDate(date.getUTCDate() + index);
                const key = date.toISOString().slice(0, 10);
                return { ...(this.days[key] || {date: key, facilities: []}), shortLabel: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'][index] };
            });
        },
        get weekLabel() { return `${this.weekDays[0].date} – ${this.weekDays[6].date}`; },
        segmentsFor(day, hour) {
            const segments = (day?.facilities || []).flatMap(facility => facility.intervals
                .filter(interval => interval.start < hour.end && interval.end > hour.start)
                .map(interval => {
                    const start = Math.max(interval.start, hour.start);
                    const end = Math.min(interval.end, hour.end);
                    const displayState = interval.state === 'available' && interval.pendingCount > 0 ? 'pending' : interval.state;
                    const displayLabel = {available: 'Available', occupied: 'Reserved · Confirmed', pending: 'Request pending · Not a confirmed booking', closed: 'Closed / Unavailable', unknown: 'Availability unconfirmed'}[displayState] || interval.label;
                    return {...interval, displayState, displayLabel, partial: start !== hour.start || end !== hour.end, time: `${this.clock(start)} – ${this.clock(end)}`};
                }));
            return segments.length ? segments : [{displayState: 'unknown', displayLabel: 'Availability unconfirmed', reasons: [], bookings: [], partial: false}];
        },
        updateUrl() {
            const url = new URL(window.location.href);
            url.searchParams.set('date', this.selected);
            url.searchParams.set('view', this.view);
            history.replaceState(null, '', url);
        },
        changeView(view) { this.view = view; this.updateUrl(); },
        selectDate(date) {
            if (date.slice(0, 7) !== this.loadedMonth) { this.pickDate(date); return; }
            if (!this.days[date]) return;
            this.selected = date;
            this.updateUrl();
        },
        pickDate(date) {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return;
            if (date.slice(0, 7) === this.loadedMonth && this.days[date]) { this.selectDate(date); return; }
            const url = new URL(window.location.href);
            url.searchParams.set('date', date);
            url.searchParams.set('month', date.slice(0, 7));
            url.searchParams.set('view', this.view);
            this.loading = true;
            window.location.assign(url);
        },
        navigate(direction) {
            const date = new Date(this.selected + 'T00:00:00Z');
            if (this.view === 'month') { date.setUTCDate(1); date.setUTCMonth(date.getUTCMonth() + direction); }
            else date.setUTCDate(date.getUTCDate() + direction * (this.view === 'week' ? 7 : 1));
            this.pickDate(date.toISOString().slice(0, 10));
        }
    };
}
