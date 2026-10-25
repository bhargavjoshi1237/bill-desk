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
 * Report totals grouped by period, portal, bank or customer, with a totals row.
 */
class BreakdownTable extends TableWidget
{
    use ResolvesDateRange;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    /** 'period', 'portal', 'bank' or 'customer'. */
    public string $dimension = 'period';

    public function table(Table $table): Table
    {
        $isAdmin = $this->isAdmin();
        $money = fn (string $column, string $label): TextColumn => TextColumn::make($column)
            ->label($label)
            ->money()
            ->alignEnd()
            ->weight(fn (array $record): ?FontWeight => $record['is_total'] ? FontWeight::Bold : null);

        return $table
            ->heading($this->heading())
            ->records(fn (): Collection => $this->rows())
            ->columns([
                TextColumn::make('name')
                    ->label($this->dimensionLabel())
                    ->description(fn (array $record): ?string => $record['mobile'] ?? null)
                    ->weight(fn (array $record): ?FontWeight => $record['is_total'] ? FontWeight::Bold : null),
                TextColumn::make('entries')
                    ->label(__('Entries'))
                    ->alignCenter(),
                $money('bill_amount', __('Bill amount')),
                $money('portal_deduction', __('Portal deduction'))->toggleable(isToggledHiddenByDefault: $this->dimension === 'period'),
                $money('service_charge', __('Service charge')),
                // Total due and received are derivable from the other columns; hidden to keep the table on screen.
                $money('total_due', __('Total due'))->toggleable(isToggledHiddenByDefault: true),
                $money('amount_received', __('Received'))->toggleable(isToggledHiddenByDefault: true),
                $money('outstanding', __('Outstanding')),
                $money('net_profit', __('Net profit (booked)'))->visible($isAdmin),
                $money('collected_amount', __('Collected in period'))->visible($this->dimension === 'period'),
                $money('collected_profit', __('Net profit (collected)'))->visible($isAdmin && $this->dimension === 'period'),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('No entries in this period'));
    }

    /**
     * @return Collection<string, array<string, mixed>>
     */
    protected function rows(): Collection
    {
        $reports = app(ReportService::class);
        $range = $this->dateRange();

        $rows = match ($this->dimension) {
            'portal' => $reports->byPortal($range),
            'bank' => $reports->byBank($range),
            'customer' => $reports->byCustomer($range, 25),
            default => $reports->byPeriod($range, $this->grouping())
                ->reject(fn (array $row): bool => ReportService::isEmptyPeriod($row))
                ->map(fn (array $row): array => [...$row, 'name' => $row['label']]),
        };

        $rows = $rows->mapWithKeys(fn (array $row, $key): array => ["row-{$key}" => [...$row, 'is_total' => false]]);

        if ($rows->isEmpty()) {
            return $rows;
        }

        $total = ['name' => $this->dimension === 'customer' ? __('Total (top 25)') : __('Total'), 'is_total' => true, 'entries' => $rows->sum('entries')];

        foreach (['bill_amount', 'portal_deduction', 'service_charge', 'total_due', 'amount_received', 'outstanding', 'net_profit', 'collected_amount', 'collected_profit'] as $column) {
            $total[$column] = Money::fromPaise($rows->sum(fn (array $row): int => Money::toPaise($row[$column] ?? 0)));
        }

        return $rows->put('total', $total);
    }

    protected function heading(): string
    {
        return match ($this->dimension) {
            'portal' => __('By portal'),
            'bank' => __('By bank'),
            'customer' => __('Top customers'),
            default => __(':grouping breakdown', ['grouping' => ReportService::groupings()[$this->grouping() ?? $this->dateRange()->suggestedGrouping()]]),
        };
    }

    protected function dimensionLabel(): string
    {
        return match ($this->dimension) {
            'portal' => __('Portal'),
            'bank' => __('Bank'),
            'customer' => __('Customer'),
            default => __('Period'),
        };
    }
}
