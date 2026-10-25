<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Exports\OutstandingExport;
use App\Exports\Spreadsheet;
use App\Filament\Widgets\AgingOverview;
use App\Filament\Widgets\OutstandingCustomersTable;
use App\Filament\Widgets\OverdueEntriesTable;
use App\Pdf\PdfDocument;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Who owes how much right now, with aging buckets.
 */
class Outstanding extends BaseDashboard
{
    protected static string $routePath = 'outstanding';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Reports;

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('Outstanding & dues');
    }

    public function getTitle(): string
    {
        return __('Outstanding & dues');
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('excel')
                    ->label(__('Excel (.xlsx)'))
                    ->icon(Heroicon::OutlinedTableCells)
                    ->action(fn () => Spreadsheet::download('outstanding-'.today()->toDateString(), OutstandingExport::sheets())),
                Action::make('pdf')
                    ->label(__('PDF'))
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->action(fn () => PdfDocument::download('outstanding', fn (): array => OutstandingExport::pdfData(), 'outstanding-'.today()->toDateString(), 'landscape')),
            ])
                ->label(__('Export'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->button()
                ->color('gray'),
        ];
    }

    public function getWidgets(): array
    {
        return [
            AgingOverview::class,
            OutstandingCustomersTable::class,
            OverdueEntriesTable::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
