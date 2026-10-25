<?php

namespace App\Exports;

use App\Filament\Widgets\OutstandingCustomersTable;
use App\Models\Customer;
use App\Models\Entry;
use App\Reports\ReportService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sheets and PDF data for the current outstanding / dues report.
 */
final class OutstandingExport
{
    /**
     * @return array<string, array{headings: array<string>, rows: iterable<array<mixed>>}>
     */
    public static function sheets(): array
    {
        $data = self::pdfData();

        return [
            __('Customers') => [
                'headings' => [__('Customer'), __('Mobile'), __('Open entries'), __('Outstanding'), __('Overdue'), __('Oldest unpaid entry'), __('Age (days)')],
                'rows' => $data['customers']->map(fn (Customer $c): array => [
                    $c->name, $c->mobile, (int) $c->open_entries_count, (float) $c->outstanding_total,
                    (float) $c->overdue_total, $c->oldest_open_entry_date ? Carbon::parse($c->oldest_open_entry_date) : null,
                    OutstandingCustomersTable::age($c),
                ]),
            ],
            __('Open entries') => [
                'headings' => [__('Entry #'), __('Date'), __('Customer'), __('Mobile'), __('Bank'), __('Total due'), __('Received'), __('Outstanding'), __('Due date'), __('Status')],
                'rows' => $data['entries']->map(fn (Entry $e): array => [
                    $e->number(), $e->entry_date, $e->customer?->name, $e->customer?->mobile, $e->bank?->name,
                    (float) $e->total_due, (float) $e->amount_received, (float) $e->outstanding, $e->due_date, $e->displayStatus(),
                ]),
            ],
            __('Aging') => [
                'headings' => [__('Age'), __('Open entries'), __('Outstanding')],
                'rows' => collect($data['aging'])->map(fn (array $b): array => [$b['label'], $b['entries'], (float) $b['outstanding']])->values(),
            ],
        ];
    }

    /**
     * @return array{position: array<string, mixed>, aging: array<string, array<string, mixed>>, customers: Collection<int, Customer>, entries: Collection<int, Entry>}
     */
    public static function pdfData(): array
    {
        $reports = app(ReportService::class);

        return [
            'position' => $reports->currentPosition(),
            'aging' => $reports->aging(),
            'customers' => $reports->outstandingCustomersQuery()->orderBy('oldest_open_entry_date')->get(),
            'entries' => Entry::query()->open()->with(['customer', 'bank'])->orderBy('entry_date')->get(),
        ];
    }
}
