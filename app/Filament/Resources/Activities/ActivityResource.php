<?php

namespace App\Filament\Resources\Activities;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\CustomerCard;
use App\Models\Entry;
use App\Models\Payment;
use App\Models\Portal;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/**
 * Read-only audit trail: who created, changed or deleted what, and when.
 */
class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Administration;

    protected static ?int $navigationSort = 9;

    protected static ?string $slug = 'activity-log';

    public static function getModelLabel(): string
    {
        return __('Activity');
    }

    public static function getNavigationLabel(): string
    {
        // Filament title-cases plural labels in the sidebar; keep sentence case like the rest of the menu.
        return __('Activity log');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Activity log');
    }

    /**
     * @return array<class-string, string>
     */
    public static function subjectTypes(): array
    {
        return [
            Entry::class => __('Entry'),
            Payment::class => __('Payment'),
            Customer::class => __('Customer'),
            CustomerCard::class => __('Credit card'),
            Portal::class => __('Portal'),
            WalletTransaction::class => __('Wallet transaction'),
            Bank::class => __('Bank'),
            User::class => __('User'),
            Setting::class => __('Setting'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function events(): array
    {
        return [
            'created' => __('Created'),
            'updated' => __('Updated'),
            'deleted' => __('Deleted'),
            'login' => __('Logged in'),
        ];
    }

    public static function subjectLabel(Activity $activity): string
    {
        $type = self::subjectTypes()[$activity->subject_type] ?? class_basename((string) $activity->subject_type);

        if (! $activity->subject_id) {
            return $activity->log_name === 'auth' ? __('Sign-in') : '—';
        }

        $subject = $activity->subject;
        $name = match (true) {
            $subject instanceof Entry, $subject instanceof Payment => $subject->number(),
            $subject instanceof Customer, $subject instanceof Portal, $subject instanceof Bank, $subject instanceof User => $subject->name,
            $subject instanceof Setting => $subject->key,
            default => '#'.$activity->subject_id,
        };

        return "{$type}: {$name}";
    }

    /**
     * Changed attributes as "old → new" pairs.
     *
     * @return array<string, string>
     */
    public static function changes(Activity $activity): array
    {
        $new = $activity->attribute_changes?->get('attributes', []) ?? [];
        $old = $activity->attribute_changes?->get('old', []) ?? [];

        $format = fn (mixed $value): string => match (true) {
            $value === null || $value === '' => '—',
            is_bool($value) => $value ? __('Yes') : __('No'),
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };

        return collect(array_keys($new + $old))
            ->mapWithKeys(fn (string $key): array => [
                $key => array_key_exists($key, $old)
                    ? $format($old[$key] ?? null).'  →  '.$format($new[$key] ?? null)
                    : $format($new[$key] ?? null),
            ])
            ->all();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('created_at')
                    ->label(__('When'))
                    ->dateTime(),
                TextEntry::make('causer.name')
                    ->label(__('User'))
                    ->placeholder(__('System')),
                TextEntry::make('event')
                    ->label(__('Action'))
                    ->formatStateUsing(fn (?string $state): string => self::events()[$state] ?? (string) $state)
                    ->badge(),
                TextEntry::make('subject')
                    ->label(__('Record'))
                    ->state(fn (Activity $record): string => self::subjectLabel($record)),
                KeyValueEntry::make('changes')
                    ->label(__('Changes'))
                    ->state(fn (Activity $record): array => self::changes($record))
                    ->keyLabel(__('Field'))
                    ->valueLabel(__('Old → New'))
                    ->columnSpanFull()
                    ->visible(fn (Activity $record): bool => filled(self::changes($record))),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['causer', 'subject']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('When'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label(__('User'))
                    ->placeholder(__('System')),
                TextColumn::make('event')
                    ->label(__('Action'))
                    ->formatStateUsing(fn (?string $state): string => self::events()[$state] ?? (string) $state)
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('subject_type')
                    ->label(__('Record'))
                    ->state(fn (Activity $record): string => self::subjectLabel($record)),
                TextColumn::make('changes')
                    ->label(__('Changed fields'))
                    ->state(fn (Activity $record): string => implode(', ', array_keys(self::changes($record))))
                    ->limit(60)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('causer_id')
                    ->label(__('User'))
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, $id) => $query
                        ->where('causer_type', (new User)->getMorphClass())
                        ->where('causer_id', $id))),
                SelectFilter::make('subject_type')
                    ->label(__('Record type'))
                    ->options(self::subjectTypes()),
                SelectFilter::make('event')
                    ->label(__('Action'))
                    ->options(self::events()),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label(__('From date')),
                        DatePicker::make('until')->label(__('Until date')),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
        ];
    }
}
