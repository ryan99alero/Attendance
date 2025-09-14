/**
 * Processing Indicator JavaScript
 * Prevents browser timeouts during long-running operations
 */

class ProcessingIndicator {
    constructor() {
        this.isProcessing = false;
        this.keepAliveInterval = null;
        this.startTime = null;
    }

    start() {
        if (this.isProcessing) return;

        this.isProcessing = true;
        this.startTime = Date.now();

        // Show visual indicator
        this.showProcessingIndicator();

        // Keep connection alive every 30 seconds
        this.keepAliveInterval = setInterval(() => {
            this.sendKeepAlive();
        }, 30000);

        // Log start
        console.log('🚀 Processing started at', new Date().toLocaleString());
    }

    stop() {
        if (!this.isProcessing) return;

        this.isProcessing = false;

        // Clear keep-alive interval
        if (this.keepAliveInterval) {
            clearInterval(this.keepAliveInterval);
            this.keepAliveInterval = null;
        }

        // Hide visual indicator
        this.hideProcessingIndicator();

        // Calculate duration
        if (this.startTime) {
            const duration = Math.round((Date.now() - this.startTime) / 1000);
            console.log(`✅ Processing completed in ${duration} seconds`);
        }
    }

    showProcessingIndicator() {
        // Create processing overlay if it doesn't exist
        if (!document.getElementById('processing-overlay')) {
            const overlay = document.createElement('div');
            overlay.id = 'processing-overlay';
            overlay.innerHTML = `
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-xl max-w-md mx-4">
                        <div class="flex items-center space-x-4">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                    Processing Attendance Records
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    Please wait while we process your data. This may take several minutes.
                                </p>
                                <div class="mt-2">
                                    <div class="text-xs text-gray-400 dark:text-gray-500" id="processing-time">
                                        Processing time: <span id="elapsed-time">0</span>s
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);

            // Update elapsed time every second
            const timeElement = document.getElementById('elapsed-time');
            if (timeElement && this.startTime) {
                this.timeInterval = setInterval(() => {
                    const elapsed = Math.floor((Date.now() - this.startTime) / 1000);
                    timeElement.textContent = elapsed;
                }, 1000);
            }
        }
    }

    hideProcessingIndicator() {
        const overlay = document.getElementById('processing-overlay');
        if (overlay) {
            overlay.remove();
        }

        if (this.timeInterval) {
            clearInterval(this.timeInterval);
            this.timeInterval = null;
        }
    }

    sendKeepAlive() {
        // Send a lightweight request to keep the session alive
        fetch('/api/keep-alive', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                timestamp: Date.now(),
                processing: true
            })
        }).catch(error => {
            console.log('Keep-alive request failed (this is normal):', error);
        });
    }

    // Static methods for easy use
    static start() {
        if (!window.processingIndicator) {
            window.processingIndicator = new ProcessingIndicator();
        }
        window.processingIndicator.start();
    }

    static stop() {
        if (window.processingIndicator) {
            window.processingIndicator.stop();
        }
    }
}

// Make it globally available
window.ProcessingIndicator = ProcessingIndicator;

// Auto-start processing indicator when Filament actions are triggered
document.addEventListener('DOMContentLoaded', function() {
    // Listen for Filament action events
    document.addEventListener('action-started', function(e) {
        if (e.detail && e.detail.action === 'process_time') {
            ProcessingIndicator.start();
        }
    });

    document.addEventListener('action-finished', function(e) {
        if (e.detail && e.detail.action === 'process_time') {
            ProcessingIndicator.stop();
        }
    });

    // Listen for Livewire events
    if (window.Livewire) {
        window.Livewire.on('processing-started', () => {
            ProcessingIndicator.start();
        });

        window.Livewire.on('processing-finished', () => {
            ProcessingIndicator.stop();
        });
    }
});