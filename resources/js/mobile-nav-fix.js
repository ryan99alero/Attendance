// Fix for mobile navigation scrolling in Filament
document.addEventListener('DOMContentLoaded', function() {
    if (window.innerWidth <= 768) {
        // Find all possible sidebar elements
        const selectors = [
            '.fi-sidebar',
            '.fi-sidebar-nav',
            '.fi-layout-sidebar',
            '[data-sidebar]',
            'nav[aria-label="Navigation"]',
            'aside[role="navigation"]',
            '.fi-layout aside'
        ];

        selectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(element => {
                element.style.overflowY = 'auto';
                element.style.webkitOverflowScrolling = 'touch';
                element.style.maxHeight = '100vh';
                element.style.position = 'relative';
            });
        });

        // Force iOS to recognize scrollable content
        setTimeout(() => {
            const sidebar = document.querySelector('.fi-sidebar, .fi-layout aside, nav[aria-label="Navigation"]');
            if (sidebar) {
                sidebar.scrollTop = 1;
                sidebar.scrollTop = 0;
            }
        }, 100);
    }
});

// Re-apply on window resize
window.addEventListener('resize', function() {
    if (window.innerWidth <= 768) {
        document.dispatchEvent(new Event('DOMContentLoaded'));
    }
});