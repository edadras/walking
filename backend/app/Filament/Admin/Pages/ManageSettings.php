<?php

namespace App\Filament\Admin\Pages;

use App\Domain\Audit\AuditLogger;
use App\Domain\Settings\Settings;
use App\Models\Admin;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
 * Central business settings. The field list is generated from config/walk.php
 * so a new setting only needs to be declared once.
 */
class ManageSettings extends Page
{
    private const GROUP_LABELS = [
        'app' => 'اپلیکیشن',
        'auth' => 'ورود و OTP',
        'security' => 'امنیت',
        'activity' => 'فعالیت',
        'health' => 'سلامت',
        'account' => 'حساب کاربری',
        'fraud' => 'ضد تقلب',
        'reward' => 'پاداش',
        'gamification' => 'سطح و XP',
        'referral' => 'دعوت از دوستان',
        'visits' => 'بازدید مکان‌های اسپانسری',
        'ads' => 'تبلیغات',
    ];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'تنظیمات';

    protected static ?string $navigationLabel = 'تنظیمات مرکزی';

    protected static ?string $title = 'تنظیمات مرکزی';

    protected static ?string $slug = 'settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof Admin && $admin->hasAbility('settings.manage');
    }

    public function mount(): void
    {
        $settings = app(Settings::class);
        $state = [];
        foreach (array_keys(config('walk.settings')) as $key) {
            $state[self::field($key)] = $settings->get($key);
        }
        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $sections = [];
        foreach (collect(config('walk.settings'))->groupBy(fn ($d) => $d['group'], preserveKeys: true) as $group => $definitions) {
            $fields = [];
            foreach ($definitions as $key => $definition) {
                $fields[] = $this->componentFor($key, $definition);
            }
            $sections[] = Section::make(self::GROUP_LABELS[$group] ?? $group)->columns(2)->schema($fields);
        }

        return $schema->components($sections)->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label('ذخیره تغییرات')->submit('save')])]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $settings = app(Settings::class);
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $old = [];
        $new = [];
        foreach (config('walk.settings') as $key => $definition) {
            $value = $this->cast($state[self::field($key)] ?? null, $definition['value']);
            if ($value !== $settings->get($key)) {
                $old[$key] = $settings->get($key);
                $new[$key] = $value;
            }
        }

        foreach ($new as $key => $value) {
            $settings->set($key, $value, $admin->id);
        }

        if ($new !== []) {
            app(AuditLogger::class)->log('settings.updated', null, $old, $new, actor: $admin);
        }

        Notification::make()->title($new === [] ? 'تغییری ثبت نشد.' : 'تنظیمات ذخیره شد.')->success()->send();
    }

    /** Livewire state keys can't contain dots. */
    private static function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    private function componentFor(string $key, array $definition): TextInput|Toggle|TagsInput
    {
        $default = $definition['value'];
        $label = $definition['description'] ?? $key;

        return match (true) {
            is_bool($default) => Toggle::make(self::field($key))->label($label),
            is_int($default) => TextInput::make(self::field($key))->label($label)->numeric()->required()->helperText($key),
            is_array($default) => TagsInput::make(self::field($key))->label($label)->helperText($key),
            default => TextInput::make(self::field($key))->label($label)->helperText($key),
        };
    }

    private function cast(mixed $value, mixed $default): mixed
    {
        return match (true) {
            is_bool($default) => (bool) $value,
            is_int($default) => (int) $value,
            is_array($default) => array_values(array_map(fn ($v) => is_numeric($v) ? (int) $v : $v, (array) $value)),
            default => $value === '' ? null : $value,
        };
    }
}
