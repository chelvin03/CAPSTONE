<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[name="expected_attendees"]').forEach(field => {
        field.min = '1';
        field.max = @js((string) \App\Support\GymCapacity::MAX_ATTENDEES);
        field.step = '1';
        const help = document.createElement('p');
        help.className = 'mt-2 text-xs text-slate-500';
        help.textContent = 'Maximum Gymnasium Capacity: 2,000 persons';
        const error = document.createElement('p');
        error.className = 'mt-2 text-sm text-red-700';
        error.setAttribute('role', 'alert');
        error.id = 'capacity-error-' + Array.from(document.querySelectorAll('input')).indexOf(field);
        field.setAttribute('aria-describedby', error.id);
        field.after(help, error);
        const validate = () => {
            field.setCustomValidity('');
            const value = Number(field.value);
            const message = value > Number(field.max)
                ? 'The expected number of attendees exceeds the MCST Gymnasium maximum capacity of 2,000 persons. Please reduce the number of attendees to continue.'
                : (!field.value || !/^[0-9]+$/.test(field.value) || !Number.isInteger(value) || value < 1 || field.validity.badInput || field.validity.stepMismatch ? 'Enter a whole number of attendees between 1 and 2,000.' : '');
            field.setCustomValidity(message);
            field.setAttribute('aria-invalid', String(Boolean(message)));
            field.style.borderColor = message ? '#dc2626' : '';
            error.textContent = message;
        };
        field.addEventListener('input', validate);
        field.addEventListener('invalid', validate);
        if (field.value) validate();
    });
});
</script>
