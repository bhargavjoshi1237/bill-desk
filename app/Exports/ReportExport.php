<?php

namespace App\Exports;

use App\Reports\ReportService;
use App\Support\DateRange;
use Illuminate\Support\Collection;

/**
 * Sheets and PDF data for the date-range report. Profit columns are only
 * included for admins.
 */
final class ReportExport
{
    /**
     * @return array<string, array{headings: array<string>, rows: iterable<array<mixed>>}>
     */
    public static function sheets(DateRange $range, ?string $grouping, bool $includeProfit): array
    {
        $data = self::pdfData($range, $grouping, $includeProfit);
        $summary = $data['summary'];

        $summaryRows = [
            [__('Period'), $range->label()],
            [__('Entries'), $summary['entries']],
            [__('Bill volume'), (float) $summary['bill_amount']],
            [__('Portal deduction'), (float) $summary['portal_deduction']],
            [__('Service charge'), (float) $summary['service_charge']],
            [__('Total due from customers'), (float) $summary['total_due']],
            [__('Received against these entries'), (float) $summary['amount_received']],
            [__('Outstanding from this period'), (float) $summary['outstanding']],
            [__('Collected from customers'), (float) $summary['collected_amount']],
        ];

        if ($includeProfit) {
            $summaryRows[] = [__('Net profit (booked)'), (float) $summary['net_profit']];
            $summaryRows[] = [__('Net profit (collected)'), (float) $summary['collected_profit']];
        }

        $sheets = [__('Summary') => ['headings' => [__('Measure'), __('Value')], 'rows' => $summaryRows]];

        foreach ($data['sections'] as $section) {
            $sheets[$section['title']] = [
                'headings' => array_values($section['columns']),
                'rows' => $section['rows']->map(fn (array $row): array => collect(array_keys($section['columns']))
                    ->map(fn (string $key) => is_numeric($row[$key] ?? null) && $key !== 'mobile' ? (float) $row[$key] : ($row[$key] ?? null))
                    ->all()),
            ];
        }

        return $sheets;
    }

    /**
     * @return array{range: DateRange, summary: array<string, mixed>, includeProfit: bool, sections: array<int, array{title: string, columns: array<string, string>, rows: Collection<int, array<string, mixed>>}>}
     */
    public static function pdfData(DateRange $range, ?string $grouping, bool $includeProfit): array
    {
        $reports = app(ReportService::class);
        $grouping ??= $range->suggestedGrouping();

        $columns = fn (string $first, array $extra = []): array => array_filter([
            'name' => $first,
            'entries' => __('Entries'),
            'bill_amount' => __('Bill amount'),
            'portal_deduction' => __('Portal deduction'),
            'service_charge' => __('Service charge'),
            'total_due' => __('Total due'),
            'amount_received' => __('Received'),
            'outstanding' => __('Outstanding'),
            'net_profit' => $includeProfit ? __('Net profit') : null,
            ...$extra,
        ]);

        return [
            'range' => $range,
            'summary' => $reports->summary($range),
            'includeProfit' => $includeProfit,
            'sections' => [
                [
                    'title' => ReportService::groupings()[$grouping],
                    'columns' => $columns(__('Period'), array_filter([
                        'collected_amount' => __('Collected'),
                        'collected_profit' => $includeProfit ? __('Profit collected') : null,
                    ])),
                    'rows' => $reports->byPeriod($range, $grouping)
                        ->reject(fn (array $row): bool => ReportService::isEmptyPeriod($row))
                        ->map(fn (array $row): array => [...$row, 'name' => $row['label']])
                        ->values(),
                ],
                [
                    'title' => __('By portal'),
                    'columns' => $columns(__('Portal')),
                    'rows' => $reports->byPortal($range)->values(),
                ],
                [
                    'title' => __('By bank'),
                    'columns' => $columns(__('Bank')),
                    'rows' => $reports->byBank($range)->values(),
                ],
                [
                    'title' => __('By customer'),
                    'columns' => ['name' => __('Customer'), 'mobile' => __('Mobile'), ...array_slice($columns(__('Customer')), 1)],
                    'rows' => $reports->byCustomer($range)->values(),
                ],
            ],
        ];
    }
}
