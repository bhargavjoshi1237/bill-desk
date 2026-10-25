<?php

namespace App\Filament\Actions;

use App\Models\Customer;
use App\Pdf\PdfDocument;
use App\Reports\CustomerStatement;
use App\Support\DateRange;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Downloads a customer's account statement PDF for a chosen period.
 */
final class CustomerStatementAction
{
    public static function make(): Action
    {
        return Action::make('statement')
            ->label(__('Statement (PDF)'))
            ->icon(Heroicon::OutlinedDocumentChartBar)
            ->color('gray')
            ->modalHeading(fn (Customer $record): string => __('Account statement: :name', ['name' => $record->name]))
            ->modalSubmitActionLabel(__('Download PDF'))
            ->modalWidth('md')
            ->fillForm(function (Customer $record): array {
                $firstEntry = $record->entries()->min('entry_date');

                return [
                    'from' => ($firstEntry ? Carbon::parse($firstEntry) : today()->startOfMonth())->toDateString(),
                    'until' => today()->toDateString(),
                ];
            })
            ->schema([
                DatePicker::make('from')->label(__('From date'))->required()->maxDate(today()),
                DatePicker::make('until')->label(__('Until date'))->required()->maxDate(today()),
            ])
            ->action(function (Customer $record, array $data) {
                $range = DateRange::between($data['from'], $data['until']);

                return PdfDocument::download('customer-statement', fn (): array => [
                    'customer' => $record,
                    'range' => $range,
                    'statement' => (new CustomerStatement($record, $range))->build(),
                ], 'statement-'.Str::slug($record->name).'-'.$range->fromDate().'-'.$range->untilDate());
            });
    }
}
