<?php

namespace App\Filament\Actions;

use App\Exports\Spreadsheet;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;

/**
 * "Export" button for a table header: downloads exactly what the table shows
 * (current filters, search and sort) as Excel or CSV.
 */
final class ExportTableAction
{
    /**
     * @param  Closure(): array<string, Closure(Model): mixed>  $columns  Column heading => value resolver.
     */
    public static function make(string $filename, Closure $columns): ActionGroup
    {
        return ActionGroup::make([
            self::format('xlsx', __('Excel (.xlsx)'), $filename, $columns),
            self::format('csv', __('CSV (.csv)'), $filename, $columns),
        ])
            ->label(__('Export'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->button();
    }

    private static function format(string $format, string $label, string $filename, Closure $columns): Action
    {
        return Action::make("export_{$format}")
            ->label($label)
            ->action(function (HasTable $livewire) use ($format, $filename, $columns) {
                $columns = $columns();

                $rows = $livewire->getFilteredSortedTableQuery()
                    ->lazy(500)
                    ->map(fn (Model $record): array => array_map(fn (Closure $value) => $value($record), $columns));

                return Spreadsheet::download(
                    $filename.'-'.now()->format('Y-m-d-His'),
                    [$filename => ['headings' => array_keys($columns), 'rows' => $rows]],
                    $format,
                );
            });
    }
}
