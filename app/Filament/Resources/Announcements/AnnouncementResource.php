<?php

namespace App\Filament\Resources\Announcements;

use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Filament\Resources\Announcements\Schemas\AnnouncementForm;
use App\Filament\Resources\Announcements\Tables\AnnouncementsTable;
use App\Models\Announcement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Announcements';

    protected static string|\UnitEnum|null $navigationGroup = 'Employee Management';

    protected static ?int $navigationSort = 40;

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user?->hasRole(['super_admin', 'admin', 'manager']) ?? false;
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user?->hasRole(['super_admin', 'admin', 'manager']) ?? false;
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user?->hasRole(['super_admin', 'admin', 'manager']) ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        return $user?->hasRole(['super_admin', 'admin', 'manager']) ?? false;
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();

        return $user?->hasRole(['super_admin', 'admin']) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return AnnouncementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AnnouncementsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnnouncements::route('/'),
            'create' => CreateAnnouncement::route('/create'),
            'edit' => EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
