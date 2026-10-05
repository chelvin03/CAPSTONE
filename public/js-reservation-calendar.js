function gymBookingCalendar() {
    return {
        bookingMonth: '', bookingDays: {}, bookingLoading: false, bookingError: '', bookingRequest: 0,
        bookingOpening: '', bookingClosing: '', bookingPeriod: '',
        get bookingAvailablePeriods() { return (this.bookingSelectedDay?.slots || []).filter(slot => slot.available); },
        get bookingTimeError() {
            if (!this.startTime || !this.endTime) return '';
            if (this.startTime < '08:00' || this.endTime > '21:00') return 'Reservations must be within 8:00 AM - 9:00 PM.';
            if (this.endTime <= this.startTime) return 'End Time must be after Start Time.';
            if (this.hasSelectedConflict) return 'Selected time overlaps with an existing reservation. Please choose from the remaining available time.';
            return '';
        },
        bookingFullAvailable() {
            const periods = this.bookingAvailablePeriods;
            const period = periods.length === 1 ? periods[0] : periods.find(slot => slot.start_time === this.bookingPeriod);
            if (!period) { this.message = 'Choose an available period to reserve in full.'; return; }
            this.bookingSelectSlot(period);
        },
        get bookingMonthLabel() {
            if (!this.bookingMonth) return '';
            const [year, month] = this.bookingMonth.split('-').map(Number);
            return new Date(year, month - 1, 1).toLocaleDateString([], {month: 'long', year: 'numeric'});
        },
        get bookingCells() {
            if (!this.bookingMonth) return [];
            const [year, month] = this.bookingMonth.split('-').map(Number);
            const cells = Array(new Date(year, month - 1, 1).getDay()).fill(null);
            for (let day = 1; day <= new Date(year, month, 0).getDate(); day++) {
                const key = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                cells.push({number: day, date: key, ...this.bookingDays[key]});
            }
            return cells;
        },
        get bookingSelectedDay() { return this.bookingDays[this.date]; },
        get bookingCanSubmit() {
            const day = this.bookingSelectedDay;
            return Boolean(!this.bookingLoading && !this.bookingError && day?.selectable && day.status !== 'full'
                && this.startTime && this.endTime > this.startTime && this.startTime >= this.bookingOpening
                && this.endTime <= this.bookingClosing && !this.hasSelectedConflict);
        },
        bookingTime(time) { return time ? new Date(`2000-01-01T${time}`).toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'}) : ''; },
        bookingCanMove(offset) {
            if (!this.bookingMonth) return false;
            const [year, month] = this.bookingMonth.split('-').map(Number);
            const target = new Date(year, month - 1 + offset, 1);
            const key = `${target.getFullYear()}-${String(target.getMonth() + 1).padStart(2, '0')}`;
            return key >= this.minimumDate.slice(0, 7) && key <= this.maximumDate.slice(0, 7);
        },
        async bookingMoveMonth(offset) {
            if (!this.bookingCanMove(offset)) return;
            const [year, month] = this.bookingMonth.split('-').map(Number);
            const target = new Date(year, month - 1 + offset, 1);
            this.bookingMonth = `${target.getFullYear()}-${String(target.getMonth() + 1).padStart(2, '0')}`;
            await this.loadBookingMonth();
        },
        async openBookingCalendar() {
            this.bookingMonth = (this.date || this.minimumDate).slice(0, 7);
            await this.loadBookingMonth();
        },
        async loadBookingMonth() {
            const request = ++this.bookingRequest;
            this.bookingLoading = true; this.bookingError = ''; this.bookingDays = {};
            try {
                if (!this.facility) throw new Error('The gymnasium is unavailable. Please contact the administrator.');
                const url = new URL(this.bookingAvailabilityUrl, window.location.origin);
                url.searchParams.set('facility_id', this.facility); url.searchParams.set('month', this.bookingMonth);
                const response = await fetch(url, {headers: {'Accept': 'application/json'}, cache: 'no-store'});
                if (!response.ok) throw new Error('Calendar availability could not be loaded. Please try again.');
                const data = await response.json();
                if (request !== this.bookingRequest) return;
                this.bookingDays = data.days; this.bookingOpening = data.opening_time; this.bookingClosing = data.closing_time;
                if (this.bookingSelectedDay) this.occupied = this.bookingSelectedDay.occupied;
            } catch (error) { if (request === this.bookingRequest) this.bookingError = error.message; }
            finally { if (request === this.bookingRequest) this.bookingLoading = false; }
        },
        bookingSelectDate(day) {
            if (this.bookingLoading || this.bookingError || !day.selectable || day.status === 'full') return;
            this.bookingPeriod = ''; this.date = day.date; this.occupied = day.occupied; this.startTime = ''; this.endTime = ''; this.message = '';
            this.updateBookingSummary();
        },
        bookingSelectSlot(slot) {
            if (!slot.available || this.bookingLoading || this.bookingError || !this.bookingSelectedDay?.selectable) return;
            this.bookingPeriod = slot.start_time; this.startTime = slot.start_time; this.endTime = slot.end_time; this.updateBookingSummary();
        },
        updateBookingSummary() {
            const values = {'Reservation Date': this.selectedDateLabel, 'Start Time': this.bookingTime(this.startTime), 'End Time': this.bookingTime(this.endTime)};
            this.summary = this.summary.map(item => Object.hasOwn(values, item.label) ? {...item, value: values[item.label]} : item);
        }
    };
}
