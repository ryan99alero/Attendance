<div class="space-y-4">
    <div class="p-4 bg-danger-50 dark:bg-danger-900/20 rounded-lg border border-danger-200 dark:border-danger-800">
        <h4 class="text-sm font-medium text-danger-800 dark:text-danger-200 mb-2">Error Message</h4>
        <p class="text-sm text-danger-700 dark:text-danger-300 whitespace-pre-wrap">{{ $task->error_message }}</p>
    </div>

    <div class="grid grid-cols-2 gap-4 text-sm">
        <div>
            <span class="text-gray-500 dark:text-gray-400">Task Name:</span>
            <p class="font-medium">{{ $task->name }}</p>
        </div>
        <div>
            <span class="text-gray-500 dark:text-gray-400">Type:</span>
            <p class="font-medium">{{ $task->getTypeLabel() }}</p>
        </div>
        <div>
            <span class="text-gray-500 dark:text-gray-400">Started:</span>
            <p class="font-medium">{{ $task->started_at?->format('M j, Y g:i A') ?? 'N/A' }}</p>
        </div>
        <div>
            <span class="text-gray-500 dark:text-gray-400">Failed:</span>
            <p class="font-medium">{{ $task->completed_at?->format('M j, Y g:i A') ?? 'N/A' }}</p>
        </div>
        @if($task->processed_records || $task->total_records)
        <div>
            <span class="text-gray-500 dark:text-gray-400">Progress:</span>
            <p class="font-medium">{{ $task->processed_records }} / {{ $task->total_records ?? '?' }} records</p>
        </div>
        @endif
        @if($task->successful_records || $task->failed_records)
        <div>
            <span class="text-gray-500 dark:text-gray-400">Results:</span>
            <p class="font-medium">
                <span class="text-success-600">{{ $task->successful_records }} successful</span>,
                <span class="text-danger-600">{{ $task->failed_records }} failed</span>
            </p>
        </div>
        @endif
    </div>
</div>
