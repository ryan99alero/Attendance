<?php

namespace App\Filament\Resources\Announcements\Tables;

use App\Models\Announcement;
use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AnnouncementsTable
{
    /**
     * @return \Illuminate\Support\Collection<int, Employee>
     */
    protected static function getPendingEmployees(Announcement $announcement, array $readEmployeeIds): \Illuminate\Support\Collection
    {
        $query = Employee::where('is_active', true);

        return match ($announcement->target_type) {
            'all' => $query->whereNotIn('id', $readEmployeeIds)->get(),
            'department' => $query->where('department_id', $announcement->department_id)
                ->whereNotIn('id', $readEmployeeIds)->get(),
            'employee' => $announcement->employee_id && ! in_array($announcement->employee_id, $readEmployeeIds)
                ? $query->where('id', $announcement->employee_id)->get()
                : collect(),
            default => collect(),
        };
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Announcement $record): string => Str::limit(strip_tags(Str::markdown($record->body)), 50)),

                TextColumn::make('target_type')
                    ->label('Audience')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Announcement::getTargetTypeOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'all' => 'success',
                        'department' => 'info',
                        'employee' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('priority')
                    ->label('Priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'low' => 'gray',
                        'normal' => 'info',
                        'high' => 'warning',
                        'urgent' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('audio_type')
                    ->label('Audio')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Announcement::getAudioTypeOptions()[$state] ?? $state)
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                IconColumn::make('require_acknowledgment')
                    ->label('Ack Required')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->placeholder('Immediately')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->placeholder('Never')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reads_count')
                    ->label('Read By')
                    ->counts('reads')
                    ->suffix(' employees')
                    ->sortable(),

                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('target_type')
                    ->label('Audience')
                    ->options(Announcement::getTargetTypeOptions()),

                SelectFilter::make('priority')
                    ->options(Announcement::getPriorityOptions()),

                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),

                TernaryFilter::make('require_acknowledgment')
                    ->label('Requires Acknowledgment')
                    ->placeholder('All')
                    ->trueLabel('Yes')
                    ->falseLabel('No'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn (Announcement $record): string => $record->title)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->infolist([
                        Section::make('Message')
                            ->schema([
                                TextEntry::make('body')
                                    ->label('')
                                    ->markdown()
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Details')
                            ->schema([
                                TextEntry::make('creator.name')
                                    ->label('Sent By'),
                                TextEntry::make('created_at')
                                    ->label('Sent At')
                                    ->dateTime('F j, Y \a\t g:i A'),
                                TextEntry::make('priority')
                                    ->label('Priority')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'low' => 'gray',
                                        'normal' => 'info',
                                        'high' => 'warning',
                                        'urgent' => 'danger',
                                        default => 'gray',
                                    }),
                                TextEntry::make('require_acknowledgment')
                                    ->label('Requires Acknowledgment')
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No'),
                            ])
                            ->columns(2),
                    ]),
                Action::make('stats')
                    ->label('Stats')
                    ->icon('heroicon-o-chart-bar')
                    ->color('gray')
                    ->modalHeading(fn (Announcement $record): string => "Acknowledgment Stats: {$record->title}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(function (Announcement $record): \Illuminate\Contracts\View\View {
                        $stats = $record->getReadStats();
                        $reads = $record->reads()->with('employee')->get();

                        $acknowledged = $reads->whereNotNull('acknowledged_at');
                        $dismissed = $reads->whereNotNull('dismissed_at')->whereNull('acknowledged_at');

                        $readEmployeeIds = $reads->pluck('employee_id')->toArray();
                        $pendingEmployees = self::getPendingEmployees($record, $readEmployeeIds);

                        return view('filament.announcements.stats-modal', [
                            'stats' => $stats,
                            'acknowledged' => $acknowledged,
                            'dismissed' => $dismissed,
                            'pendingEmployees' => $pendingEmployees,
                        ]);
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
