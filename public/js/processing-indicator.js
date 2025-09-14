/**
 * Processing Indicator for Filament Actions
 * Prevents browser timeouts and provides visual feedback
 */
document.addEventListener('DOMContentLoaded', function() {
    let processingOverlay = null;
    let keepAliveInterval = null;
    let startTime = null;
    let timeInterval = null;

    function showProcessing() {
        if (processingOverlay) return;

        startTime = Date.now();

        // Create processing overlay
        processingOverlay = document.createElement('div');
        processingOverlay.id = 'processing-overlay';
        processingOverlay.className = 'fixed inset-0 bg-black/50 backdrop-blur-sm z-[60] flex items-center justify-center';
        processingOverlay.innerHTML = `
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-xl max-w-md mx-4 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
                        <div class="absolute inset-0 rounded-full border-2 border-gray-200 dark:border-gray-600"></div>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Processing Attendance Records
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            Processing attendance data. Please wait...
                        </p>
                        <div class="mt-3 space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-500 dark:text-gray-400">Processing time:</span>
                                <span class="font-mono font-medium text-gray-700 dark:text-gray-300" id="elapsed-time">0s</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="bg-primary-600 h-2 rounded-full animate-pulse" style="width: 45%"></div>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 italic">
                                💡 Page will remain responsive
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(processingOverlay);

        // Update elapsed time
        if (timeInterval) clearInterval(timeInterval);
        timeInterval = setInterval(() => {
            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            const timeEl = document.getElementById('elapsed-time');
            if (timeEl) {
                const minutes = Math.floor(elapsed / 60);
                const seconds = elapsed % 60;
                timeEl.textContent = minutes > 0
                    ? `${minutes}m ${seconds}s`
                    : `${seconds}s`;
            }
        }, 1000);

        // Keep connection alive
        if (keepAliveInterval) clearInterval(keepAliveInterval);
        keepAliveInterval = setInterval(() => {
            fetch('/api/keep-alive', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ timestamp: Date.now(), processing: true })
            }).catch(() => {}); // Ignore errors
        }, 25000); // Every 25 seconds

        console.log('🚀 Processing started');
    }

    function hideProcessing() {
        if (processingOverlay) {
            processingOverlay.remove();
            processingOverlay = null;
        }

        if (keepAliveInterval) {
            clearInterval(keepAliveInterval);
            keepAliveInterval = null;
        }

        if (timeInterval) {
            clearInterval(timeInterval);
            timeInterval = null;
        }

        if (startTime) {
            const duration = Math.round((Date.now() - startTime) / 1000);
            console.log(`✅ Processing completed in ${duration} seconds`);
            startTime = null;
        }
    }

    // Clean up step notifications
    function cleanupNotifications() {
        const notifications = document.querySelectorAll('[data-fi-notification]');
        notifications.forEach(notification => {
            const title = notification.textContent;
            if (title.includes('Processing Step') || title.includes('Processing Started')) {
                const closeButton = notification.querySelector('[data-fi-notification-close]');
                if (closeButton) {
                    closeButton.click();
                } else {
                    notification.remove();
                }
            }
        });
    }

    // Hook into Filament table actions
    document.addEventListener('click', function(e) {
        const button = e.target.closest('[data-action="process_time"]');
        if (button) {
            showProcessing();
        }
    });

    // Listen for processing completion
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'childList') {
                const notifications = document.querySelectorAll('[data-fi-notification]');
                notifications.forEach(notification => {
                    const title = notification.textContent;
                    if (title.includes('Processing Complete') || title.includes('Processing Failed')) {
                        setTimeout(() => {
                            hideProcessing();
                            cleanupNotifications();
                        }, 2000);
                    }
                });
            }
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', hideProcessing);
});