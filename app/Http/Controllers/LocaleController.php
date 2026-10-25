<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, User::LOCALES), 404);

        $request->user()->update(['locale' => $locale]);

        return redirect()->back(fallback: route('filament.admin.pages.dashboard'));
    }
}
