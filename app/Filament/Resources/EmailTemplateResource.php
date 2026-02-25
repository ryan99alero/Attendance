<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailTemplateResource\Pages\EditEmailTemplate;
use App\Filament\Resources\EmailTemplateResource\Pages\ListEmailTemplates;
use App\Models\EmailTemplate;
use App\Services\EmailTemplateService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Email Templates';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static ?int $navigationSort = 50;

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user?->hasRole('super_admin') ?? false;
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user?->hasRole('super_admin') ?? false;
    }

    public static function canView($record): bool
    {
        $user = auth()->user();

        return $user?->hasRole('super_admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return false; // Templates are created from definitions only
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        return $user?->hasRole('super_admin') ?? false;
    }

    public static function canDelete($record): bool
    {
        return false; // Templates should not be deleted
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'lg' => 4])
                    ->schema([
                        TextInput::make('name')
                            ->label('Template Name')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpan(['default' => 1, 'lg' => 2]),

                        TextInput::make('key')
                            ->label('Template Key')
                            ->disabled()
                            ->dehydrated(false),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->inline(false),
                    ]),

                Placeholder::make('description')
                    ->label('Description')
                    ->content(fn (EmailTemplate $record): ?string => $record->getDescription()),

                TextInput::make('subject')
                    ->label('Email Subject')
                    ->required()
                    ->helperText('Use {{variable}} syntax for dynamic content'),

                MarkdownEditor::make('body')
                    ->label('Email Body')
                    ->required()
                    ->helperText('Use {{variable}} for values, {{#variable}}...{{/variable}} for conditional blocks'),

                Section::make('Available Variables')
                    ->description('Click any variable to copy it to your clipboard')
                    ->collapsed()
                    ->schema([
                        Placeholder::make('variables')
                            ->label('')
                            ->content(function (EmailTemplate $record): HtmlString {
                                $variables = $record->getAvailableVariables();

                                if (empty($variables)) {
                                    return new HtmlString('<p class="text-sm text-gray-500">No variables available</p>');
                                }

                                // Group variables by table
                                $grouped = [];
                                foreach ($variables as $key => $info) {
                                    $parts = explode('.', $key);
                                    $table = $parts[0] ?? 'other';
                                    $grouped[$table][$key] = $info;
                                }

                                $html = '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">';

                                foreach ($grouped as $table => $vars) {
                                    $html .= '<div style="border: 1px solid rgba(156, 163, 175, 0.3); border-radius: 0.5rem; padding: 1rem;">';
                                    $html .= '<h4 style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(156, 163, 175, 0.3);">'.ucfirst(str_replace('_', ' ', $table)).'</h4>';
                                    $html .= '<div style="display: flex; flex-direction: column; gap: 0.5rem;">';

                                    foreach ($vars as $key => $info) {
                                        $html .= '<div style="background: rgba(156, 163, 175, 0.1); border-radius: 0.375rem; padding: 0.5rem 0.75rem; cursor: pointer;" onclick="navigator.clipboard.writeText(\'{{'.$key.'}}\'); this.style.background=\'rgba(34, 197, 94, 0.2)\'; setTimeout(() => this.style.background=\'rgba(156, 163, 175, 0.1)\', 800);" title="Click to copy">';
                                        $html .= '<code style="font-family: monospace; font-size: 0.8rem; color: #f97316;">{{'.$key.'}}</code>';
                                        $html .= '<div style="font-size: 0.75rem; color: rgba(156, 163, 175, 0.9); margin-top: 0.125rem;">'.$info['label'].'</div>';
                                        $html .= '</div>';
                                    }

                                    $html .= '</div>';
                                    $html .= '</div>';
                                }

                                $html .= '</div>';

                                return new HtmlString($html);
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Template')
                    ->sortable()
                    ->searchable()
                    ->description(fn (EmailTemplate $record): ?string => $record->getDescription()),

                TextColumn::make('key')
                    ->label('Key')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('subject')
                    ->label('Subject')
                    ->limit(50)
                    ->searchable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('updated_at')
                    ->label('Last Modified')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All templates')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('sendTest')
                    ->label('Test')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->form([
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->label('Send test email to')
                            ->default(fn () => auth()->user()->email),
                    ])
                    ->action(function (array $data, EmailTemplate $record) {
                        try {
                            app(EmailTemplateService::class)->sendTest($record->key, $data['email']);

                            Notification::make()
                                ->title('Test email sent!')
                                ->body("A test email was sent to {$data['email']}")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed to send test email')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('resetToDefault')
                    ->label('Reset')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Reset to Default')
                    ->modalDescription('This will restore the subject and body to their original defaults. Any customizations will be lost.')
                    ->action(function (EmailTemplate $record) {
                        $success = app(EmailTemplateService::class)->resetToDefault($record);

                        if ($success) {
                            Notification::make()
                                ->title('Template reset to default')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Could not reset template')
                                ->body('No default template definition found.')
                                ->warning()
                                ->send();
                        }
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailTemplates::route('/'),
            'edit' => EditEmailTemplate::route('/{record}/edit'),
        ];
    }
}
