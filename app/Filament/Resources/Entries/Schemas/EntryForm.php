<?php

namespace App\Filament\Resources\Entries\Schemas;

use App\Enums\CardNetwork;
use App\Enums\PaymentMode;
use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\CustomerCard;
use App\Models\Entry;
use App\Models\Portal;
use App\Support\Money;
use App\Support\Settings;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class EntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                self::customerSection(),
                                self::billSection(),
                                self::initialPaymentSection(),
                            ])
                            ->columnSpan(['lg' => 2]),
                        // Stays in view while the longer form on the left scrolls.
                        self::calculationSection()
                            ->columnSpan(['lg' => 1])
                            ->extraAttributes(['class' => 'lg:sticky lg:top-24 lg:self-start']),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function customerSection(): Section
    {
        return Section::make(__('Customer & card'))
            ->icon(Heroicon::OutlinedUser)
            ->schema([
                Select::make('customer_id')
                    ->label(__('Customer'))
                    ->relationship('customer', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Customer $record): string => $record->displayName())
                    ->searchable(['name', 'mobile'])
                    ->preload()
                    ->required()
                    ->default(fn (): ?int => request()->integer('customer') ?: null)
                    ->live()
                    ->createOptionForm(CustomerForm::quickCreateFields())
                    ->createOptionModalHeading(__('New customer'))
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        // Pick the card automatically when the customer has only one.
                        $cards = CustomerCard::query()->where('customer_id', $state)->get();
                        $card = $cards->count() === 1 ? $cards->first() : null;

                        $set('customer_card_id', $card?->id);
                        $set('bank_id', $card?->bank_id);
                    })
                    ->columnSpanFull(),
                Select::make('customer_card_id')
                    ->label(__('Credit card'))
                    ->options(fn (Get $get): array => CustomerCard::query()
                        ->with('bank')
                        ->where('customer_id', $get('customer_id'))
                        ->get()
                        ->mapWithKeys(fn (CustomerCard $card): array => [$card->id => $card->label()])
                        ->all())
                    ->placeholder(__('Select a card (optional)'))
                    ->disabled(fn (Get $get): bool => blank($get('customer_id')))
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        if ($bankId = CustomerCard::query()->whereKey($state)->value('bank_id')) {
                            $set('bank_id', $bankId);
                        }
                    })
                    ->createOptionForm(self::cardFields())
                    ->createOptionModalHeading(__('Add card'))
                    ->createOptionUsing(fn (array $data, Get $get): int => CustomerCard::create([
                        ...$data,
                        'customer_id' => $get('customer_id'),
                    ])->getKey())
                    ->helperText(__('Only the last 4 digits are stored, never the full card number.')),
                Select::make('bank_id')
                    ->label(__('Bank'))
                    ->relationship('bank', 'name', fn (Builder $query, ?Entry $record) => $query
                        ->where('is_active', true)
                        ->when($record?->bank_id, fn (Builder $query, int $id) => $query->orWhereKey($id)))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabled(fn (Get $get): bool => filled($get('customer_card_id')))
                    ->dehydrated()
                    ->helperText(fn (Get $get): ?string => filled($get('customer_card_id')) ? __('Taken from the selected card.') : null),
            ])
            ->columns(2);
    }

    private static function billSection(): Section
    {
        return Section::make(__('Bill & portal'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->schema([
                DatePicker::make('entry_date')
                    ->label(__('Date'))
                    ->default(today())
                    ->maxDate(today())
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set, string $operation): void {
                        if ($operation === 'create' && $state) {
                            $set('due_date', Carbon::parse($state)->addDays(Settings::dueDays())->toDateString());
                        }
                    }),
                TextInput::make('bill_amount')
                    ->label(__('Bill amount'))
                    ->numeric()
                    ->prefix('₹')
                    ->minValue(1)
                    ->step(0.01)
                    ->required()
                    ->live(onBlur: true)
                    ->rules([
                        fn (Get $get, ?Entry $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                            if (! $record?->exists) {
                                return;
                            }

                            $totalDue = Money::add($value, Money::percentOf($value, $get('service_charge_percent')));

                            if (Money::toPaise($totalDue) < Money::toPaise($record->amount_received)) {
                                $fail(__('The total due can’t be less than the :amount already received.', [
                                    'amount' => Money::format($record->amount_received),
                                ]));
                            }
                        },
                    ]),
                DatePicker::make('due_date')
                    ->label(__('Repayment due date'))
                    ->default(today()->addDays(Settings::dueDays()))
                    ->minDate(fn (Get $get) => $get('entry_date'))
                    ->helperText(__('Shown as overdue after this date if not fully paid.')),
                Select::make('portal_id')
                    ->label(__('Portal'))
                    ->relationship('portal', 'name', fn (Builder $query, ?Entry $record) => $query
                        ->where('is_active', true)
                        ->when($record?->portal_id, fn (Builder $query, int $id) => $query->orWhereKey($id)))
                    ->getOptionLabelFromRecordUsing(fn (Portal $record): string => "{$record->name} (".Money::percent($record->charge_percent).')')
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        if ($portal = Portal::find($state)) {
                            $set('portal_charge_percent', (float) $portal->charge_percent);
                        }
                    }),
                TextInput::make('portal_charge_percent')
                    ->label(__('Portal charge'))
                    ->numeric()
                    ->suffix('%')
                    ->minValue(0)
                    ->maxValue(100)
                    ->step(0.001)
                    ->required()
                    ->live(onBlur: true)
                    ->helperText(__('Filled from the portal; change it for this entry if needed.')),
                TextInput::make('service_charge_percent')
                    ->label(__('Service charge'))
                    ->numeric()
                    ->suffix('%')
                    ->minValue(0)
                    ->maxValue(100)
                    ->step(0.001)
                    ->default(fn (): float => Settings::serviceChargePercent())
                    ->required()
                    ->live(onBlur: true)
                    ->helperText(__('Charged to the customer on the bill amount.')),
                TextInput::make('reference')
                    ->label(__('Portal transaction ID'))
                    ->maxLength(255),
                // Shares the last row with the transaction ID, so all three rows are full.
                Textarea::make('notes')
                    ->label(__('Notes'))
                    ->rows(2)
                    ->columnSpan(['lg' => 2]),
            ])
            ->columns(3);
    }

    /**
     * Money the customer pays straight away, recorded as the first payment
     * (handled by CreateEntry). Only on create; later payments go through
     * "Record payment".
     */
    private static function initialPaymentSection(): Section
    {
        return Section::make(__('Amount received now'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->description(__('Leave at 0 if the customer will pay later.'))
            ->visibleOn('create')
            ->schema([
                TextInput::make('initial_payment.amount')
                    ->label(__('Amount received'))
                    ->numeric()
                    ->prefix('₹')
                    ->minValue(0)
                    ->step(0.01)
                    ->default(0)
                    ->live(onBlur: true)
                    ->suffixAction(
                        Action::make('fullAmount')
                            ->label(__('Full amount'))
                            ->icon(Heroicon::OutlinedCheckCircle)
                            ->tooltip(__('Customer paid the full amount'))
                            ->action(fn (Get $get, Set $set) => $set('initial_payment.amount', self::preview($get)['total_due'])),
                    )
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            $totalDue = self::preview($get)['total_due'];

                            if (Money::toPaise($value) > Money::toPaise($totalDue)) {
                                $fail(__('This is more than the total due (:amount).', ['amount' => Money::format($totalDue)]));
                            }
                        },
                    ]),
                TextInput::make('initial_payment.reference')
                    ->label(__('Reference / UTR / Cheque no.'))
                    ->maxLength(255),
                ToggleButtons::make('initial_payment.mode')
                    ->label(__('Payment mode'))
                    ->options(PaymentMode::class)
                    ->default(PaymentMode::Cash->value)
                    ->inline()
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    private static function calculationSection(): Section
    {
        $money = fn (string $key): Closure => fn (Get $get): string => Money::format(self::preview($get)[$key]);

        return Section::make(__('Calculation'))
            ->icon(Heroicon::OutlinedCalculator)
            ->description(__('Updates as you type.'))
            ->schema([
                TextEntry::make('preview_bill')
                    ->label(__('Bill amount'))
                    ->state($money('bill_amount')),
                TextEntry::make('preview_portal_deduction')
                    ->label(fn (Get $get): string => __('Portal deduction (:percent)', ['percent' => Money::percent($get('portal_charge_percent'))]))
                    ->state($money('portal_deduction'))
                    ->color('danger'),
                TextEntry::make('preview_service_charge')
                    ->label(fn (Get $get): string => __('Service charge (:percent)', ['percent' => Money::percent($get('service_charge_percent'))]))
                    ->state($money('service_charge')),
                TextEntry::make('preview_total_due')
                    ->label(__('Total due from customer'))
                    ->state($money('total_due'))
                    ->weight(FontWeight::Bold)
                    ->size(TextSize::Large),
                TextEntry::make('preview_outstanding')
                    ->label(__('Outstanding after amount received'))
                    ->state($money('outstanding'))
                    ->color(fn (Get $get): string => Money::toPaise(self::preview($get)['outstanding']) > 0 ? 'warning' : 'success')
                    ->visibleOn('create'),
                TextEntry::make('preview_net_profit')
                    ->label(__('Net profit'))
                    ->state($money('net_profit'))
                    ->color(fn (Get $get): string => Money::toPaise(self::preview($get)['net_profit']) < 0 ? 'danger' : 'success')
                    ->weight(FontWeight::Bold)
                    ->visible(fn (): bool => auth()->user()->isAdmin()),
                TextEntry::make('preview_wallet')
                    ->label(__('Portal wallet after this entry'))
                    ->state(function (Get $get, ?Entry $record): ?string {
                        $balance = self::walletAfter($get, $record);

                        return $balance === null ? '—' : Money::format($balance);
                    })
                    ->color(fn (Get $get, ?Entry $record): string => Money::toPaise(self::walletAfter($get, $record) ?? 0) < 0 ? 'danger' : 'gray')
                    ->helperText(fn (Get $get, ?Entry $record): ?string => Money::toPaise(self::walletAfter($get, $record) ?? 0) < 0
                        ? __('Not enough balance in the portal wallet. You can still save, but top up the wallet.')
                        : __('Bill + portal deduction is debited from the wallet.')),
            ]);
    }

    /**
     * Live calculation from the current form state.
     *
     * @return array<string, string>
     */
    public static function preview(Get $get): array
    {
        $bill = (float) $get('bill_amount');
        $portalDeduction = Money::percentOf($bill, $get('portal_charge_percent'));
        $serviceCharge = Money::percentOf($bill, $get('service_charge_percent'));
        $totalDue = Money::add($bill, $serviceCharge);

        return [
            'bill_amount' => Money::fromPaise(Money::toPaise($bill)),
            'portal_deduction' => $portalDeduction,
            'service_charge' => $serviceCharge,
            'total_due' => $totalDue,
            'net_profit' => Money::subtract($serviceCharge, $portalDeduction),
            'outstanding' => Money::subtract($totalDue, $get('initial_payment.amount')),
        ];
    }

    private static function walletAfter(Get $get, ?Entry $record): ?string
    {
        $portal = Portal::find($get('portal_id'));

        if (! $portal) {
            return null;
        }

        $preview = self::preview($get);

        return Money::subtract(
            $portal->walletBalance($record?->getKey()),
            Money::add($preview['bill_amount'], $preview['portal_deduction']),
        );
    }

    /**
     * @return array<Component>
     */
    public static function cardFields(): array
    {
        return [
            Select::make('bank_id')
                ->label(__('Bank'))
                ->options(fn (): array => Bank::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->required(),
            TextInput::make('last_four')
                ->label(__('Last 4 digits'))
                ->length(4)
                ->regex('/^\d{4}$/')
                ->validationMessages(['regex' => __('Enter exactly 4 digits.')]),
            Select::make('network')
                ->label(__('Card network'))
                ->options(CardNetwork::class),
        ];
    }
}
