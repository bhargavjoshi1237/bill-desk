<?php

namespace App\Filament\Resources\Portals;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Portals\Pages\CreatePortal;
use App\Filament\Resources\Portals\Pages\EditPortal;
use App\Filament\Resources\Portals\Pages\ListPortals;
use App\Filament\Resources\Portals\Pages\ViewPortal;
use App\Filament\Resources\Portals\RelationManagers\WalletTransactionsRelationManager;
use App\Filament\Resources\Portals\Schemas\PortalForm;
use App\Filament\Resources\Portals\Schemas\PortalInfolist;
use App\Filament\Resources\Portals\Tables\PortalsTable;
use App\Models\Portal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PortalResource extends Resource
{
    protected static ?string $model = Portal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::GlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Portals;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('Portal / account');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Portals & accounts');
    }

    public static function getNavigationBadge(): ?string
    {
        $low = Portal::query()->where('is_active', true)->get()->filter->isLowOnBalance()->count();

        return $low > 0 ? (string) $low : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Portals with low wallet balance');
    }

    public static function form(Schema $schema): Schema
    {
        return PortalForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PortalInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PortalsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            WalletTransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPortals::route('/'),
            'create' => CreatePortal::route('/create'),
            'view' => ViewPortal::route('/{record}'),
            'edit' => EditPortal::route('/{record}/edit'),
        ];
    }
}
