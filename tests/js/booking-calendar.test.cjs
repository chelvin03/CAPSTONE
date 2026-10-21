const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const context = vm.createContext({});
vm.runInContext(fs.readFileSync('public/js-reservation-calendar.js', 'utf8'), context);

function calendar(occupied = [], full = false) {
    const state = context.gymBookingCalendar();
    Object.assign(state, {date: '2026-10-29', startTime: '09:00', endTime: '11:00', occupied,
        bookingOpening: '07:00', bookingClosing: '24:00', summary: [],
        bookingDays: {'2026-10-29': {selectable: true, status: full ? 'full' : 'partial', occupied}}});
    Object.defineProperty(state, 'hasSelectedConflict', {get() {
        return this.occupied.some(slot => this.startTime < slot.end_time && this.endTime > slot.start_time);
    }});
    return state;
}

test('occupied schedules automatically allow waiting requests while available schedules remain bookable', () => {
    assert.equal(calendar().bookingCanSubmit, true);
    const state = calendar([{start_time: '08:30', end_time: '11:30', status: 'reserved'}]);
    assert.equal(state.bookingCanSubmit, true);
    assert.equal(state.waitingListEligible, true);
});

test('automatic waiting list permits fully booked days but never administrator closures', () => {
    const state = calendar([{start_time: '07:00', end_time: '24:00', status: 'reserved'}], true);
    assert.equal(state.bookingCanSubmit, true);
    state.occupied.push({start_time: '10:00', end_time: '12:00', status: 'blackout'});
    assert.equal(state.bookingCanSubmit, false);
    assert.match(state.bookingTimeError, /blocked/);
});

test('automatic waiting list preserves operating hours minimum duration and half-hour boundaries', () => {
    const state = calendar();
    for (const [start, end] of [['05:00', '08:00'], ['09:00', '10:00'], ['09:15', '11:30'], ['11:00', '09:00']]) {
        state.startTime = start; state.endTime = end;
        assert.equal(state.bookingCanSubmit, false);
    }
});

test('calendar loading failure blocks submission instead of assuming waiting-list eligibility', () => {
    const state = calendar();
    state.bookingError = 'Could not load';
    assert.equal(state.bookingCanSubmit, false);
});
