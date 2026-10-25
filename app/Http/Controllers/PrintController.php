<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Entry;
use App\Models\Payment;
use App\Pdf\PdfDocument;
use App\Reports\CustomerStatement;
use App\Support\DateRange;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Printable PDFs opened in a browser tab: receipts and statements.
 */
class PrintController extends Controller
{
    public function entryReceipt(Entry $entry): Response
    {
        Gate::authorize('view', $entry);

        $entry->load(['customer', 'bank', 'portal', 'card', 'payments', 'creator']);

        return PdfDocument::inline('entry-receipt', ['entry' => $entry], 'receipt-'.$entry->number());
    }

    public function paymentReceipt(Payment $payment): Response
    {
        Gate::authorize('view', $payment);

        $payment->load(['entry.customer', 'entry.bank', 'entry.card', 'receiver']);

        return PdfDocument::inline('payment-receipt', ['payment' => $payment], 'receipt-'.$payment->number());
    }

    public function customerStatement(Request $request, Customer $customer): Response
    {
        Gate::authorize('view', $customer);

        $range = DateRange::between(
            $request->date('from') ?? $customer->entries()->min('entry_date'),
            $request->date('until') ?? today(),
        );

        return PdfDocument::inline('customer-statement', fn (): array => [
            'customer' => $customer,
            'range' => $range,
            'statement' => (new CustomerStatement($customer, $range))->build(),
        ], 'statement-'.Str::slug($customer->name));
    }
}
