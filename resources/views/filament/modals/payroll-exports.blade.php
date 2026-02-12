<div class="overflow-hidden rounded-xl bg-white ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
     x-data="{
        deleteExport(id, row) {
            if (!confirm('Delete this export?')) return;

            fetch('{{ url('payroll/export') }}/' + id, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: '_method=DELETE'
            })
            .then(response => {
                if (response.ok) {
                    row.remove();
                } else {
                    alert('Failed to delete export');
                }
            })
            .catch(() => alert('Failed to delete export'));
        }
     }">
    <table class="fi-ta-table w-full">
        <thead>
            <tr>
                <th class="fi-ta-header-cell px-4 py-4 text-start text-sm font-semibold text-gray-950 dark:text-white">
                    Provider
                </th>
                <th class="fi-ta-header-cell px-4 py-4 text-start text-sm font-semibold text-gray-950 dark:text-white">
                    File
                </th>
                <th class="fi-ta-header-cell px-4 py-4 text-start text-sm font-semibold text-gray-950 dark:text-white">
                    Status
                </th>
                <th class="fi-ta-header-cell px-4 py-4 text-center text-sm font-semibold text-gray-950 dark:text-white">
                    Records
                </th>
                <th class="fi-ta-header-cell px-4 py-4 text-end text-sm font-semibold text-gray-950 dark:text-white">
                    Size
                </th>
                <th class="fi-ta-header-cell px-4 py-4 text-start text-sm font-semibold text-gray-950 dark:text-white">
                    Exported
                </th>
                <th class="fi-ta-header-cell px-4 py-4 w-16">
                </th>
            </tr>
        </thead>
        <tbody>
            @forelse($exports as $export)
                @php
                    $isDownloadable = $export->isCompleted() && $export->fileExists();
                    $downloadUrl = $isDownloadable ? route('payroll.export.download', $export->id) : null;
                @endphp
                <tr class="fi-ta-row transition duration-75 hover:bg-gray-50 dark:hover:bg-white/5">
                    <td class="fi-ta-cell px-4 py-5 text-sm text-gray-950 dark:text-white whitespace-nowrap {{ $isDownloadable ? 'cursor-pointer' : '' }}"
                        @if($isDownloadable) @click="window.open('{{ $downloadUrl }}', '_blank')" @endif>
                        {{ $export->integrationConnection?->name ?? 'Unknown' }}
                    </td>
                    <td class="fi-ta-cell px-4 py-5 max-w-[280px] {{ $isDownloadable ? 'cursor-pointer' : '' }}"
                        @if($isDownloadable) @click="window.open('{{ $downloadUrl }}', '_blank')" @endif>
                        <div class="flex items-start gap-2">
                            <span class="fi-badge fi-color-gray inline-flex items-center justify-center rounded-md text-xs font-medium ring-1 ring-inset px-1.5 py-0.5 bg-gray-50 text-gray-600 ring-gray-600/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20 shrink-0">
                                {{ strtoupper($export->format) }}
                            </span>
                            <span class="text-sm text-gray-500 dark:text-gray-400 break-all {{ $isDownloadable ? 'text-primary-600 dark:text-primary-400' : '' }}">
                                {{ $export->file_name }}
                            </span>
                        </div>
                    </td>
                    <td class="fi-ta-cell px-4 py-5 whitespace-nowrap {{ $isDownloadable ? 'cursor-pointer' : '' }}"
                        @if($isDownloadable) @click="window.open('{{ $downloadUrl }}', '_blank')" @endif>
                        @if($export->isCompleted())
                            <span class="fi-badge fi-color-success inline-flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 py-1 bg-success-50 text-success-600 ring-success-600/10 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/20">
                                <x-filament::icon icon="heroicon-m-check-circle" class="h-4 w-4" />
                                Completed
                            </span>
                        @elseif($export->isProcessing())
                            <span class="fi-badge fi-color-warning inline-flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 py-1 bg-warning-50 text-warning-600 ring-warning-600/10 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/20">
                                <x-filament::icon icon="heroicon-m-arrow-path" class="h-4 w-4 animate-spin" />
                                Processing
                            </span>
                        @elseif($export->isFailed())
                            <span class="fi-badge fi-color-danger inline-flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 py-1 bg-danger-50 text-danger-600 ring-danger-600/10 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/20" title="{{ $export->error_message }}">
                                <x-filament::icon icon="heroicon-m-x-circle" class="h-4 w-4" />
                                Failed
                            </span>
                        @else
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ ucfirst($export->status) }}</span>
                        @endif
                    </td>
                    <td class="fi-ta-cell px-4 py-5 text-center text-sm text-gray-500 dark:text-gray-400 {{ $isDownloadable ? 'cursor-pointer' : '' }}"
                        @if($isDownloadable) @click="window.open('{{ $downloadUrl }}', '_blank')" @endif>
                        {{ $export->employee_count ?? '-' }}
                    </td>
                    <td class="fi-ta-cell px-4 py-5 text-end text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap {{ $isDownloadable ? 'cursor-pointer' : '' }}"
                        @if($isDownloadable) @click="window.open('{{ $downloadUrl }}', '_blank')" @endif>
                        {{ $export->fileExists() ? $export->getFileSizeForHumans() : '-' }}
                    </td>
                    <td class="fi-ta-cell px-4 py-5 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap {{ $isDownloadable ? 'cursor-pointer' : '' }}"
                        @if($isDownloadable) @click="window.open('{{ $downloadUrl }}', '_blank')" @endif>
                        {{ $export->exported_at?->diffForHumans() ?? '-' }}
                    </td>
                    <td class="fi-ta-cell px-4 py-5">
                        @if(!$export->isProcessing())
                            <button type="button"
                                    x-ref="row{{ $export->id }}"
                                    @click.stop="deleteExport({{ $export->id }}, $el.closest('tr'))"
                                    class="fi-icon-btn relative flex items-center justify-center rounded-lg outline-none transition duration-75 focus-visible:ring-2 h-8 w-8 text-gray-400 hover:text-danger-500 dark:text-gray-500 dark:hover:text-danger-400 cursor-pointer"
                                    title="Delete">
                                <x-filament::icon icon="heroicon-m-trash" class="h-5 w-5" />
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="fi-ta-cell px-3 py-6 text-center">
                        <div class="fi-ta-empty-state mx-auto grid max-w-lg justify-items-center text-center">
                            <div class="fi-ta-empty-state-icon-ctn mb-4 rounded-full bg-gray-100 p-3 dark:bg-gray-500/20">
                                <x-filament::icon icon="heroicon-o-document" class="h-6 w-6 text-gray-500 dark:text-gray-400" />
                            </div>
                            <h4 class="fi-ta-empty-state-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                                No exports
                            </h4>
                            <p class="fi-ta-empty-state-description mt-1 text-sm text-gray-500 dark:text-gray-400">
                                No exports found for this pay period.
                            </p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
