<?php

namespace App\Filament\Resources\Banks;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Banks\Pages\ManageBanks;
use App\Models\Bank;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class BankResource extends Resource
{
    protected static ?string $model = Bank::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Administration;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('Bank');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Banks');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Bank name'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label(__('Active'))
                    ->helperText(__('Inactive banks are hidden when adding new entries and cards.'))
                    ->default(true),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['entries', 'cards'])->withSum('entries as entries_volume', 'bill_amount'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Bank'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('cards_count')
                    ->label(__('Cards'))
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('entries_count')
                    ->label(__('Entries'))
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('entries_volume')
                    ->label(__('Total bill volume'))
                    ->money()
                    ->sortable()
                    ->alignEnd()
                    ->placeholder('—'),
                ToggleColumn::make('is_active')
                    ->label(__('Active')),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('Active')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBanks::route('/'),
        ];
    }
}
