<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Exports\ReportExport;
use App\Exports\Spreadsheet;
use App\Filament\Concerns\HasDateRangeFilters;
use App\Filament\Widgets\BreakdownTable;
use App\Filament\Widgets\CollectionsTrendChart;
use App\Filament\Widgets\PeriodStatsOverview;
use App\Filament\Widgets\ProfitTrendChart;
use App\Pdf\PdfDocument;
use App\Reports\ReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Date-range report: totals, a day/week/month/year breakdown, and splits by
 * portal, bank and customer, exportable to Excel and PDF.
 */
class Reports extends BaseDashboard
{
    use HasDateRangeFilters;
    use HasFiltersForm;

    protected static string $routePath = 'reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Reports;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('Date-range report');
    }

    public function getTitle(): string
    {
        return __('Date-range report');
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    ...$this->dateRangeFilterComponents(),
                    Select::make('grouping')
                        ->label(__('Group by'))
                        ->options(['auto' => __('Automatic'), ...ReportService::groupings()])
                        ->default('auto')
                        ->selectablePlaceholder(false)
                        ->native(false),
                ])
                ->columns(4)
                ->columnSpanFull(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('excel')
                    ->label(__('Excel (.xlsx)'))
                    ->icon(Heroicon::OutlinedTableCells)
                    ->action(fn () => Spreadsheet::download(
                        'report-'.$this->getDateRange()->fromDate().'-to-'.$this->getDateRange()->untilDate(),
                        ReportExport::sheets($this->getDateRange(), $this->grouping(), auth()->user()->isAdmin()),
                    )),
                Action::make('pdf')
                    ->label(__('PDF'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->action(fn () => PdfDocument::download(
                        'report',
                        fn (): array => ReportExport::pdfData($this->getDateRange(), $this->grouping(), auth()->user()->isAdmin()),
                        'report-'.$this->getDateRange()->fromDate().'-to-'.$this->getDateRange()->untilDate(),
                        'landscape',
                    )),
            ])
                ->label(__('Export'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->button()
                ->color('gray'),
        ];
    }

    private function grouping(): ?string
    {
        $grouping = $this->filters['grouping'] ?? null;

        return array_key_exists((string) $grouping, ReportService::groupings()) ? $grouping : null;
    }

    public function getWidgets(): array
    {
        return [
            PeriodStatsOverview::class,
            CollectionsTrendChart::make(['span' => 'full']),
            ProfitTrendChart::make(['span' => 'full']),
            BreakdownTable::make(['dimension' => 'period']),
            BreakdownTable::make(['dimension' => 'portal']),
            BreakdownTable::make(['dimension' => 'bank']),
            BreakdownTable::make(['dimension' => 'customer']),
        ];
    }

    public function getColumns(): int|array
    {
        return ['md' => 2, 'xl' => 6];
    }
}
