<?php

namespace App\Pdf;

use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfDocument;
use Closure;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Renders the Blade views in resources/views/pdf as A4 PDFs.
 *
 * PDFs are always rendered in English: dompdf can't shape Devanagari text,
 * so Hindi labels would print as broken glyphs.
 */
final class PdfDocument
{
    /**
     * @param  array<string, mixed>|Closure(): array<string, mixed>  $data  A closure is evaluated in English, for data that contains translated labels.
     */
    public static function make(string $view, array|Closure $data, string $orientation = 'portrait'): DomPdfDocument
    {
        $locale = app()->getLocale();
        app()->setLocale('en');

        try {
            return Pdf::loadView("pdf.{$view}", [
                ...value($data),
                'business' => Settings::all(),
                'generatedAt' => now(),
            ])->setPaper('a4', $orientation);
        } finally {
            app()->setLocale($locale);
        }
    }

    /**
     * @param  array<string, mixed>|Closure(): array<string, mixed>  $data
     */
    public static function inline(string $view, array|Closure $data, string $filename, string $orientation = 'portrait'): Response
    {
        return self::make($view, $data, $orientation)->stream("{$filename}.pdf");
    }

    /**
     * @param  array<string, mixed>|Closure(): array<string, mixed>  $data
     */
    public static function download(string $view, array|Closure $data, string $filename, string $orientation = 'portrait'): StreamedResponse
    {
        $output = self::make($view, $data, $orientation)->output();

        return response()->streamDownload(fn () => print ($output), "{$filename}.pdf", ['Content-Type' => 'application/pdf']);
    }
}
