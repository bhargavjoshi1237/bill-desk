<?php

namespace App\Filament\Resources\Entries\Pages;

use App\Enums\EntryStatus;
use App\Filament\Resources\Entries\EntryResource;
use App\Models\Entry;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListEntries extends ListRecords
{
    protected static string $resource = EntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('New entry')),
        ];
    }

    public function getTabs(): array
    {
        $tab = fn (EntryStatus $status, callable $scope): Tab => Tab::make($status->getLabel())
            ->icon($status->getIcon())
            ->badge(fn (): int => $scope(Entry::query())->count())
            ->badgeColor($status->getColor())
            ->modifyQueryUsing($scope);

        return [
            'all' => Tab::make(__('All')),
            'unpaid' => $tab(EntryStatus::Unpaid, fn (Builder $query) => $query->where('status', EntryStatus::Unpaid)),
            'partial' => $tab(EntryStatus::Partial, fn (Builder $query) => $query->where('status', EntryStatus::Partial)),
            'overdue' => $tab(EntryStatus::Overdue, fn (Builder $query) => $query->overdue()),
            'paid' => $tab(EntryStatus::Paid, fn (Builder $query) => $query->where('status', EntryStatus::Paid)),
        ];
    }
}
