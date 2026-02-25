<?php

namespace App\Filament\Resources\EmailTemplateResource\Pages;

use App\Filament\Resources\EmailTemplateResource;
use App\Services\EmailTemplateService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditEmailTemplate extends EditRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label('Send Test Email')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->form([
                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->label('Send test email to')
                        ->default(fn () => auth()->user()->email),
                ])
                ->action(function (array $data) {
                    try {
                        app(EmailTemplateService::class)->sendTest($this->record->key, $data['email']);

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
                ->label('Reset to Default')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reset to Default')
                ->modalDescription('This will restore the subject and body to their original defaults. Any customizations will be lost.')
                ->action(function () {
                    $success = app(EmailTemplateService::class)->resetToDefault($this->record);

                    if ($success) {
                        Notification::make()
                            ->title('Template reset to default')
                            ->success()
                            ->send();

                        $this->fillForm();
                    } else {
                        Notification::make()
                            ->title('Could not reset template')
                            ->body('No default template definition found.')
                            ->warning()
                            ->send();
                    }
                }),
        ];
    }
}
