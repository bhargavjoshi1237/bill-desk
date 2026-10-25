<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Enums\PaymentMode;
use App\Models\Entry;
use App\Models\Payment;
use App\Support\Money;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PaymentForm
{
    /**
     * Standalone payment form, where the entry is picked from a list.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Payment details'))
                    ->schema([
                        self::entrySelect(),
                        ...self::fields(fn (Get $get): ?Entry => Entry::find($get('entry_id'))),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The payment fields, for any context that knows which entry is being paid.
     *
     * @param  Closure  $resolveEntry  Returns the entry being paid; resolved with Filament's
     *                                 parameter injection, so it may ask for $get, $record, $livewire…
     * @return array<Component>
     */
    public static function fields(Closure $resolveEntry): array
    {
        return [
            DatePicker::make('paid_on')
                ->label(__('Payment date'))
                ->default(today())
                ->maxDate(today())
                ->required(),
            TextInput::make('amount')
                ->label(__('Amount received'))
                ->numeric()
                ->prefix('₹')
                ->minValue(0.01)
                ->step(0.01)
                ->required()
                ->helperText(function (Component $component, $record = null) use ($resolveEntry): ?string {
                    $entry = $component->evaluate($resolveEntry);
                    $payment = $record instanceof Payment ? $record : null;

                    return $entry
                        ? __('Maximum allowed: :amount', ['amount' => Money::format(self::available($entry, $payment))])
                        : null;
                })
                ->rules([
                    fn (Component $component, $record = null): Closure => function (string $attribute, mixed $value, Closure $fail) use ($component, $record, $resolveEntry): void {
                        $entry = $component->evaluate($resolveEntry);
                        $payment = $record instanceof Payment ? $record : null;

                        if ($entry && Money::toPaise($value) > Money::toPaise(self::available($entry, $payment))) {
                            $fail(__('This is more than the customer owes on this entry (:amount).', [
                                'amount' => Money::format(self::available($entry, $payment)),
                            ]));
                        }
                    },
                ]),
            ToggleButtons::make('mode')
                ->label(__('Payment mode'))
                ->options(PaymentMode::class)
                ->default(PaymentMode::Cash)
                ->inline()
                ->required()
                ->columnSpanFull(),
            TextInput::make('reference')
                ->label(__('Reference / UTR / Cheque no.'))
                ->maxLength(255),
            Textarea::make('notes')
                ->label(__('Notes'))
                ->rows(2),
        ];
    }

    /**
     * How much more can be received against an entry. When editing a payment,
     * its own original amount is available again.
     */
    public static function available(Entry $entry, ?Payment $payment = null): string
    {
        $available = Money::toPaise($entry->outstanding);

        if ($payment?->exists && (int) $payment->getOriginal('entry_id') === $entry->getKey()) {
            $available += Money::toPaise($payment->getOriginal('amount'));
        }

        return Money::fromPaise(max($available, 0));
    }

    public static function entrySelect(): Select
    {
        return Select::make('entry_id')
            ->label(__('Entry'))
            ->required()
            ->searchable()
            ->native(false)
            ->live()
            ->default(fn (): ?int => request()->integer('entry') ?: null)
            ->options(fn ($record): array => self::entryOptions(
                Entry::query()->open()->latest('entry_date')->latest('id')->limit(25),
                $record instanceof Payment ? $record->entry_id : null,
            ))
            ->getSearchResultsUsing(fn (string $search): array => self::entryOptions(
                Entry::query()
                    ->open()
                    ->where(function (Builder $query) use ($search): void {
                        $id = (int) preg_replace('/\D/', '', $search);

                        $query
                            ->whereHas('customer', fn (Builder $query) => $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('mobile', 'like', "%{$search}%"))
                            ->when($id > 0, fn (Builder $query) => $query->orWhereKey($id));
                    })
                    ->latest('entry_date')
                    ->limit(50),
            ))
            ->getOptionLabelUsing(fn ($value): ?string => ($entry = Entry::with('customer')->find($value)) ? self::entryLabel($entry) : null)
            ->afterStateUpdated(function (?string $state, Set $set): void {
                if ($entry = Entry::find($state)) {
                    $set('amount', $entry->outstanding);
                }
            })
            ->columnSpanFull();
    }

    /**
     * @param  Builder<Entry>  $query
     * @return array<int, string>
     */
    private static function entryOptions(Builder $query, ?int $includeId = null): array
    {
        $entries = $query->with('customer')->get();

        if ($includeId && ! $entries->contains('id', $includeId)) {
            $entries->prepend(Entry::with('customer')->find($includeId));
        }

        return $entries->filter()->mapWithKeys(fn (Entry $entry): array => [$entry->id => self::entryLabel($entry)])->all();
    }

    public static function entryLabel(Entry $entry): string
    {
        return __(':number · :customer (:mobile) · :date · Due :amount', [
            'number' => $entry->number(),
            'customer' => $entry->customer?->name,
            'mobile' => $entry->customer?->mobile,
            'date' => $entry->entry_date->format('d-m-Y'),
            'amount' => Money::format($entry->outstanding),
        ]);
    }
}
