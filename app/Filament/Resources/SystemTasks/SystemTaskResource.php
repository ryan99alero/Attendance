<?php

namespace App\Filament\Resources\SystemTasks;

use App\Filament\Resources\SystemTasks\Pages\ManageSystemTasks;
use App\Models\SystemTask;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SystemTaskResource extends Resource
{
    protected static ?string $model = SystemTask::class;

    protected static ?string $navigationLabel = 'Task Monitor';

    protected static ?string $modelLabel = 'System Task';

    protected static ?string $pluralModelLabel = 'System Tasks';

    protected static string|\UnitEnum|null $navigationGroup = 'System & Hardware';

    protected static ?int $navigationSort = 100;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    public static function getNavigationBadge(): ?string
    {
        $count = SystemTask::active()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll(fn () => SystemTask::active()->exists() ? '3s' : null)
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (SystemTask $record): string => $record->getTypeColor())
                    ->formatStateUsing(fn (SystemTask $record): string => $record->getTypeLabel())
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (SystemTask $record): ?string => $record->description)
                    ->wrap(),

                IconColumn::make('status')
                    ->icon(fn (SystemTask $record): string => $record->getStatusIcon())
                    ->color(fn (SystemTask $record): string => $record->getStatusColor())
                    ->tooltip(fn (SystemTask $record): string => ucfirst($record->status)),

                TextColumn::make('progress')
                    ->label('Progress')
                    ->formatStateUsing(function (SystemTask $record): string {
                        if ($record->isCompleted() || $record->isFailed()) {
                            return $record->getProgressText();
                        }

                        $bar = self::renderProgressBar($record->progress);

                        return "{$bar} {$record->progress}%";
                    })
                    ->html()
                    ->wrap(),

                TextColumn::make('progress_message')
                    ->label('Status Message')
                    ->limit(50)
                    ->tooltip(fn (SystemTask $record): ?string => $record->progress_message)
                    ->toggleable(),

                TextColumn::make('processed_records')
                    ->label('Records')
                    ->formatStateUsing(function (SystemTask $record): string {
                        if ($record->total_records) {
                            $success = $record->successful_records;
                            $failed = $record->failed_records > 0 ? " <span class='text-danger-500'>({$record->failed_records} failed)</span>" : '';

                            return "{$record->processed_records} / {$record->total_records}{$failed}";
                        }

                        return '-';
                    })
                    ->html(),

                TextColumn::make('creator.name')
                    ->label('Started By')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('duration')
                    ->label('Duration')
                    ->state(fn (SystemTask $record): ?string => $record->getDuration())
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Queued')
                    ->dateTime('M j, g:i A')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        SystemTask::STATUS_PENDING => 'Pending',
                        SystemTask::STATUS_PROCESSING => 'Processing',
                        SystemTask::STATUS_COMPLETED => 'Completed',
                        SystemTask::STATUS_FAILED => 'Failed',
                        SystemTask::STATUS_CANCELLED => 'Cancelled',
                    ])
                    ->multiple(),

                SelectFilter::make('type')
                    ->options([
                        SystemTask::TYPE_IMPORT => 'Import',
                        SystemTask::TYPE_EXPORT => 'Export',
                        SystemTask::TYPE_PROCESSING => 'Processing',
                        SystemTask::TYPE_SYNC => 'Sync',
                    ])
                    ->multiple(),

                SelectFilter::make('time_range')
                    ->label('Time Range')
                    ->options([
                        'today' => 'Today',
                        'week' => 'This Week',
                        'month' => 'This Month',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'today' => $query->whereDate('created_at', today()),
                            'week' => $query->where('created_at', '>=', now()->startOfWeek()),
                            'month' => $query->where('created_at', '>=', now()->startOfMonth()),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (SystemTask $record): bool => $record->hasOutputFile())
                    ->url(fn (SystemTask $record): ?string => $record->getOutputFileUrl())
                    ->openUrlInNewTab(),

                Action::make('view_error')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(fn (SystemTask $record): bool => $record->isFailed() && $record->error_message)
                    ->modalHeading('Error Details')
                    ->modalContent(fn (SystemTask $record) => view('filament.modals.task-error', ['task' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Action::make('cancel')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (SystemTask $record): bool => $record->isActive())
                    ->requiresConfirmation()
                    ->action(fn (SystemTask $record) => $record->markCancelled()),

                Action::make('reset_dismiss')
                    ->label('Reset & Dismiss')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (SystemTask $record): bool => $record->isFailed() || $record->status === SystemTask::STATUS_CANCELLED)
                    ->requiresConfirmation()
                    ->modalHeading('Reset & Dismiss Task')
                    ->modalDescription('This will reset the related Pay Period (if any) so you can try again, and remove this task from the list.')
                    ->action(function (SystemTask $record) {
                        $record->resetRelatedModel();
                        $record->delete();

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Task Dismissed')
                            ->body('The task has been removed and related items have been reset.')
                            ->send();
                    }),

                DeleteAction::make()
                    ->visible(fn (SystemTask $record): bool => ! $record->isActive() && ! $record->isFailed()),
            ])
            ->toolbarActions([
                Action::make('clear_completed')
                    ->label('Clear Completed')
                    ->icon('heroicon-o-trash')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Clear Completed Tasks')
                    ->modalDescription('This will delete all completed and failed tasks older than 24 hours. Associated export files will also be removed.')
                    ->action(function () {
                        $count = SystemTask::whereIn('status', [
                            SystemTask::STATUS_COMPLETED,
                            SystemTask::STATUS_FAILED,
                            SystemTask::STATUS_CANCELLED,
                        ])
                            ->where('completed_at', '<', now()->subDay())
                            ->each(function (SystemTask $task) {
                                // Delete associated export file if exists
                                if ($task->output_file_path && file_exists($task->output_file_path)) {
                                    @unlink($task->output_file_path);
                                }
                                $task->delete();
                            });

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Tasks Cleared')
                            ->body('Old completed tasks have been removed.')
                            ->send();
                    }),

                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->deselectRecordsAfterCompletion()
                        ->before(function ($records) {
                            // Delete associated files before deleting records
                            foreach ($records as $task) {
                                if ($task->output_file_path && file_exists($task->output_file_path)) {
                                    @unlink($task->output_file_path);
                                }
                            }
                        }),
                ]),
            ])
            ->emptyStateHeading('No system tasks')
            ->emptyStateDescription('Background tasks like imports, exports, and processing will appear here.')
            ->emptyStateIcon('heroicon-o-queue-list');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSystemTasks::route('/'),
        ];
    }

    protected static function renderProgressBar(int $progress): string
    {
        $color = match (true) {
            $progress >= 100 => 'bg-success-500',
            $progress >= 50 => 'bg-primary-500',
            default => 'bg-warning-500',
        };

        return "<div class='w-24 h-2 bg-gray-200 rounded-full overflow-hidden dark:bg-gray-700'>
            <div class='{$color} h-full rounded-full transition-all duration-300' style='width: {$progress}%'></div>
        </div>";
    }
}
