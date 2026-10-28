<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\DailyCatchup as DailyCatchupPage;
use App\Filament\Resources\Portals\PortalResource;
use App\Reports\DailyCatchup;
use App\Support\Money;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

/**
 * Every bank account and portal: opening balance, the day's movements and
 * the closing (latest) balance, with a totals row.
 */
class DailyCatchupTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function table(Table $table): Table
    {
        $bold = fn (array $record): ?FontWeight => $record['is_total'] ? FontWeight::Bold : null;
        $money = fn (string $column, string $label): TextColumn => TextColumn::make($column)
            ->label($label)
            ->money()
            ->alignEnd()
            ->weight($bold);

        return $table
            ->heading(__('Accounts & portals on :date', ['date' => DailyCatchupPage::dateFrom($this->pageFilters)->format('d-m-Y')]))
            ->records(fn (): Collection => $this->rows())
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->weight(FontWeight::Bold)
                    ->icon(fn (array $record): ?Heroicon => $record['is_low'] ? Heroicon::OutlinedExclamationTriangle : null)
                    ->iconColor('danger'),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->placeholder(''),
                $money('opening', __('Opening balance')),
                $money('top_ups', __('Top-ups'))->color('success'),
                $money('withdrawals', __('Withdrawals'))->color('danger'),
                $money('received', __('Received for entries'))->color('success'),
                $money('sent', __('Sent for entries'))->color('danger'),
                $money('closing', __('Closing balance'))
                    ->weight(FontWeight::Bold)
                    ->color(fn (array $record): string => $record['is_low'] ? 'danger' : 'success'),
            ])
            ->recordUrl(fn (array $record): ?string => $record['is_total'] ? null : PortalResource::getUrl('view', ['record' => $record['id']]))
            ->paginated(false)
            ->emptyStateHeading(__('No portals or bank accounts yet'));
    }

    /**
     * @return Collection<string, array<string, mixed>>
     */
    protected function rows(): Collection
    {
        $rows = (new DailyCatchup(DailyCatchupPage::dateFrom($this->pageFilters)))->rows()
            ->mapWithKeys(fn (array $row): array => ["portal-{$row['id']}" => [...$row, 'is_total' => false]]);

        if ($rows->isEmpty()) {
            return $rows;
        }

        $total = ['id' => null, 'name' => __('Total'), 'type' => null, 'is_total' => true];

        foreach (['opening', 'top_ups', 'withdrawals', 'received', 'sent', 'closing'] as $column) {
            $total[$column] = Money::fromPaise($rows->sum(fn (array $row): int => Money::toPaise($row[$column])));
        }

        $total['is_low'] = Money::toPaise($total['closing']) < 0;

        return $rows->put('total', $total);
    }
}
