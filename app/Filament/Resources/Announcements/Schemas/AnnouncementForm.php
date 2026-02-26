<?php

namespace App\Filament\Resources\Announcements\Schemas;

use App\Models\Announcement;
use App\Models\Department;
use App\Models\Employee;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AnnouncementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Announcement Content')
                    ->description('The message to display to employees')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Enter announcement title'),

                        MarkdownEditor::make('body')
                            ->label('Message')
                            ->required()
                            ->columnSpanFull()
                            ->helperText('The full announcement message'),
                    ]),

                Section::make('Targeting')
                    ->description('Who should see this announcement')
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('target_type')
                                    ->label('Audience')
                                    ->options(Announcement::getTargetTypeOptions())
                                    ->default(Announcement::TARGET_ALL)
                                    ->required()
                                    ->live()
                                    ->helperText('Who should receive this announcement'),

                                Select::make('department_id')
                                    ->label('Department')
                                    ->options(Department::pluck('name', 'id'))
                                    ->searchable()
                                    ->visible(fn (Get $get): bool => $get('target_type') === Announcement::TARGET_DEPARTMENT)
                                    ->required(fn (Get $get): bool => $get('target_type') === Announcement::TARGET_DEPARTMENT),

                                Select::make('employee_id')
                                    ->label('Employee')
                                    ->options(Employee::where('is_active', true)->pluck('full_names', 'id'))
                                    ->searchable()
                                    ->visible(fn (Get $get): bool => $get('target_type') === Announcement::TARGET_EMPLOYEE)
                                    ->required(fn (Get $get): bool => $get('target_type') === Announcement::TARGET_EMPLOYEE),
                            ]),
                    ]),

                Section::make('Time Clock Settings')
                    ->description('How the announcement appears on time clocks')
                    ->icon('heroicon-o-speaker-wave')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('audio_type')
                                    ->label('Audio Alert')
                                    ->options(Announcement::getAudioTypeOptions())
                                    ->default(Announcement::AUDIO_NONE)
                                    ->required()
                                    ->helperText('How to alert the employee at the time clock'),

                                Select::make('priority')
                                    ->label('Priority')
                                    ->options(Announcement::getPriorityOptions())
                                    ->default(Announcement::PRIORITY_NORMAL)
                                    ->required(),
                            ]),

                        Toggle::make('require_acknowledgment')
                            ->label('Require Acknowledgment')
                            ->helperText('Employee must acknowledge they read the announcement'),
                    ]),

                Section::make('Scheduling')
                    ->description('When the announcement should be active')
                    ->icon('heroicon-o-calendar')
                    ->collapsed()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                DateTimePicker::make('starts_at')
                                    ->label('Start Date/Time')
                                    ->helperText('Leave empty to start immediately'),

                                DateTimePicker::make('expires_at')
                                    ->label('Expiry Date/Time')
                                    ->helperText('Leave empty for no expiry'),

                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('Inactive announcements are not shown'),
                            ]),
                    ]),
            ]);
    }
}
