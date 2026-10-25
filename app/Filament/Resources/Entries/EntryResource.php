<?php

namespace App\Filament\Resources\Entries;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Entries\Pages\CreateEntry;
use App\Filament\Resources\Entries\Pages\EditEntry;
use App\Filament\Resources\Entries\Pages\ListEntries;
use App\Filament\Resources\Entries\Pages\ViewEntry;
use App\Filament\Resources\Entries\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Entries\Schemas\EntryForm;
use App\Filament\Resources\Entries\Schemas\EntryInfolist;
use App\Filament\Resources\Entries\Tables\EntriesTable;
use App\Models\Entry;
use App\Support\Money;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class EntryResource extends Resource
{
    protected static ?string $model = Entry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Operations;

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('Entry');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Entries');
    }

    public static function getNavigationBadge(): ?string
    {
        $overdue = Entry::query()->overdue()->count();

        return $overdue > 0 ? (string) $overdue : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Overdue entries');
    }

    public static function form(Schema $schema): Schema
    {
        return EntryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EntryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EntriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record ? $record->number() : static::getModelLabel();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['customer.name', 'customer.mobile', 'reference'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('customer');
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->number().' · '.$record->customer?->name;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            __('Date') => $record->entry_date->format('d-m-Y'),
            __('Bill amount') => Money::format($record->bill_amount),
            __('Outstanding') => Money::format($record->outstanding),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEntries::route('/'),
            'create' => CreateEntry::route('/create'),
            'view' => ViewEntry::route('/{record}'),
            'edit' => EditEntry::route('/{record}/edit'),
        ];
    }
}
