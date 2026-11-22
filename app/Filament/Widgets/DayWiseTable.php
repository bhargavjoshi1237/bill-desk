<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ResolvesDateRange;
use App\Reports\ReportService;
use App\Support\Money;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

/**
 * Bills paid, service charge and profit for each day of the dashboard
 * period, newest day first, with a totals row.
 */
class DayWiseTable extends TableWidget
{
    use ResolvesDateRange;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $money = fn (string $column, string $label): TextColumn => TextColumn::make($column)
            ->label($label)
            ->money()
            ->alignEnd()
            ->weight(fn (array $record): ?FontWeight => $record['is_total'] ? FontWeight::Bold : null);

        return $table
            ->heading(__('Day-wise: :period', ['period' => $this->dateRange()->label()]))
            ->records(fn (): Collection => $this->rows())
            ->columns([
                TextColumn::make('label')
                    ->label(__('Date'))
                    ->weight(fn (array $record): ?FontWeight => $record['is_total'] ? FontWeight::Bold : null),
                TextColumn::make('entries')
                    ->label(__('Entries'))
                    ->alignCenter(),
                $money('bill_amount', __('Bills paid')),
                $money('service_charge', __('Service charge')),
                $money('net_profit', __('Profit'))
                    ->color(fn (array $record): ?string => Money::toPaise($record['net_profit']) < 0 ? 'danger' : 'success')
                    ->visible($this->isAdmin()),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('No entries in this period'));
    }

    /**
     * @return Collection<string, array<string, mixed>>
     */
    protected function rows(): Collection
    {
        $rows = app(ReportService::class)->byPeriod($this->dateRange(), 'day')
            ->filter(fn (array $row): bool => $row['entries'] > 0)
            ->sortKeysDesc()
            ->mapWithKeys(fn (array $row, string $day): array => ["day-{$day}" => [...$row, 'is_total' => false]]);

        if ($rows->isEmpty()) {
            return $rows;
        }

        $total = ['label' => __('Total'), 'is_total' => true, 'entries' => $rows->sum('entries')];

        foreach (['bill_amount', 'service_charge', 'net_profit'] as $column) {
            $total[$column] = Money::fromPaise($rows->sum(fn (array $row): int => Money::toPaise($row[$column])));
        }

        return $rows->put('total', $total);
    }
}
