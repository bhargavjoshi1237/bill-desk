<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Filament\Resources\Entries\Schemas\EntryForm;
use App\Models\Customer;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Customer details'))
                    ->icon(Heroicon::OutlinedUser)
                    ->schema([
                        ...self::quickCreateFields(),
                        Textarea::make('address')
                            ->label(__('Address'))
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label(__('Notes'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make(__('Credit cards'))
                    ->icon(Heroicon::OutlinedCreditCard)
                    ->description(__('Only the last 4 digits are stored, never the full card number.'))
                    ->schema([
                        Repeater::make('cards')
                            ->hiddenLabel()
                            ->relationship()
                            ->schema(EntryForm::cardFields())
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel(__('Add card'))
                            ->itemLabel(fn (array $state): ?string => filled($state['last_four'] ?? null) ? '•••• '.$state['last_four'] : null),
                    ])
                    ->columnSpanFull(),
                Section::make(__('ID proof (optional)'))
                    ->icon(Heroicon::OutlinedIdentification)
                    ->collapsible()
                    ->collapsed(fn (?Customer $record): bool => blank($record?->aadhaar_number) && blank($record?->pan_number))
                    ->schema([
                        TextInput::make('aadhaar_number')
                            ->label(__('Aadhaar number'))
                            ->mask('9999 9999 9999')
                            ->stripCharacters(' ')
                            ->regex('/^\d{12}$/')
                            ->validationMessages(['regex' => __('Aadhaar number must be 12 digits.')]),
                        TextInput::make('pan_number')
                            ->label(__('PAN number'))
                            ->maxLength(10)
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->rule('regex:/^[A-Za-z]{5}\d{4}[A-Za-z]$/')
                            ->validationMessages(['regex' => __('PAN must look like ABCDE1234F.')]),
                        FileUpload::make('id_proof_files')
                            ->label(__('ID proof documents'))
                            ->helperText(__('Photos or PDF of Aadhaar / PAN. Stored privately.'))
                            ->disk('local')
                            ->directory('id-proofs')
                            ->visibility('private')
                            ->multiple()
                            ->maxFiles(4)
                            ->maxSize(4096)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                            ->openable()
                            ->downloadable()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Name, mobile and city: enough to create a customer from inside the entry form.
     *
     * @return array<Component>
     */
    public static function quickCreateFields(): array
    {
        return [
            TextInput::make('name')
                ->label(__('Customer name'))
                ->required()
                ->maxLength(255),
            TextInput::make('mobile')
                ->label(__('Mobile number'))
                ->tel()
                ->prefix('+91')
                ->required()
                ->maxLength(15)
                ->rules([
                    fn ($record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        $mobile = Customer::normalizeMobile((string) $value);

                        if (! preg_match('/^[6-9]\d{9}$/', (string) $mobile)) {
                            $fail(__('Enter a valid 10-digit mobile number.'));

                            return;
                        }

                        $taken = Customer::query()
                            ->where('mobile', $mobile)
                            ->when($record instanceof Customer && $record->exists, fn ($query) => $query->whereKeyNot($record->getKey()))
                            ->first();

                        if ($taken) {
                            $fail(__('This mobile number already belongs to :name.', ['name' => $taken->name]));
                        }
                    },
                ]),
            TextInput::make('city')
                ->label(__('City'))
                ->maxLength(255),
        ];
    }
}
