<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\HasFormContentWidth;
use App\Support\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Business details and the defaults used on new entries.
 *
 * @property-read Schema $form
 */
class ManageSettings extends Page
{
    use HasFormContentWidth;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Administration;

    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('Settings');
    }

    public function getTitle(): string
    {
        return __('Settings');
    }

    public function mount(): void
    {
        $this->form->fill(Settings::all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Business details'))
                    ->description(__('Printed on receipts, statements and reports.'))
                    ->schema([
                        TextInput::make('business_name')
                            ->label(__('Business name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('business_phone')
                            ->label(__('Phone'))
                            ->tel()
                            ->maxLength(30),
                        Textarea::make('business_address')
                            ->label(__('Address'))
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('receipt_footer')
                            ->label(__('Receipt footer'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make(__('Defaults for new entries'))
                    ->description(__('Changing these does not affect entries that are already saved.'))
                    ->schema([
                        TextInput::make('default_service_charge_percent')
                            ->label(__('Default service charge'))
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.001)
                            ->required(),
                        TextInput::make('default_due_days')
                            ->label(__('Repayment due after (days)'))
                            ->integer()
                            ->minValue(0)
                            ->maxValue(365)
                            ->required(),
                    ])
                    ->columns(2),
                Section::make(__('WhatsApp reminder'))
                    ->schema([
                        Textarea::make('whatsapp_template')
                            ->label(__('Message template'))
                            ->rows(3)
                            ->required()
                            ->helperText(__('Placeholders: {name}, {amount}, {business}, {due_date}, {entry}')),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label(__('Save settings'))
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        Settings::set($this->form->getState());

        Notification::make()
            ->success()
            ->title(__('Settings saved'))
            ->send();
    }
}
