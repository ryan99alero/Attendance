/**
 * Global date field keyboard shortcuts
 * Press 'T' in a date/datetime field to set it to today's date
 */
document.addEventListener('DOMContentLoaded', function() {
    // Listen for keydown on the document, delegating to date inputs
    document.addEventListener('keydown', function(event) {
        // Only handle 'T' key (case insensitive)
        if (event.key.toLowerCase() !== 't') {
            return;
        }

        const activeElement = document.activeElement;

        // Check if we're in a date or datetime input
        const isDateInput = activeElement && (
            activeElement.type === 'date' ||
            activeElement.type === 'datetime-local' ||
            activeElement.hasAttribute('x-data') && activeElement.closest('[wire\\:model*="date"]') ||
            activeElement.closest('.fi-fo-date-time-picker') ||
            activeElement.closest('[x-data*="datePickerFormComponent"]')
        );

        // Also check for Filament's flatpickr date inputs
        const isFlatpickrInput = activeElement && (
            activeElement.classList.contains('flatpickr-input') ||
            activeElement.closest('.flatpickr-wrapper')
        );

        if (isDateInput || isFlatpickrInput) {
            event.preventDefault();

            const today = new Date();
            const formattedDate = today.toISOString().split('T')[0]; // YYYY-MM-DD

            // For datetime fields, include time
            if (activeElement.type === 'datetime-local' ||
                activeElement.closest('.fi-fo-date-time-picker')) {
                const hours = String(today.getHours()).padStart(2, '0');
                const minutes = String(today.getMinutes()).padStart(2, '0');
                activeElement.value = `${formattedDate}T${hours}:${minutes}`;
            } else {
                activeElement.value = formattedDate;
            }

            // Trigger input event for Livewire/Alpine reactivity
            activeElement.dispatchEvent(new Event('input', { bubbles: true }));
            activeElement.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
});

// Also handle Alpine.js/Livewire date pickers using a MutationObserver
// to catch dynamically added date inputs
const observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        mutation.addedNodes.forEach(function(node) {
            if (node.nodeType === 1) { // Element node
                // Find any date inputs in the added content
                const dateInputs = node.querySelectorAll ?
                    node.querySelectorAll('input[type="date"], input[type="datetime-local"], .flatpickr-input') :
                    [];

                dateInputs.forEach(function(input) {
                    input.addEventListener('keydown', function(event) {
                        if (event.key.toLowerCase() === 't') {
                            event.preventDefault();

                            const today = new Date();
                            const formattedDate = today.toISOString().split('T')[0];

                            if (input.type === 'datetime-local') {
                                const hours = String(today.getHours()).padStart(2, '0');
                                const minutes = String(today.getMinutes()).padStart(2, '0');
                                input.value = `${formattedDate}T${hours}:${minutes}`;
                            } else {
                                input.value = formattedDate;
                            }

                            input.dispatchEvent(new Event('input', { bubbles: true }));
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    });
                });
            }
        });
    });
});

observer.observe(document.body, { childList: true, subtree: true });
