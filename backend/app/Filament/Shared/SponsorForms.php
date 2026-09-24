<?php

namespace App\Filament\Shared;

use App\Enums\DiscountType;
use App\Enums\LocationStatus;
use App\Enums\VerificationMethod;
use App\Models\Campaign;
use App\Models\Location;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;

/**
 * Form fields shared by the admin and sponsor panels. `$scope` restricts
 * relationship options to one sponsor (sponsor panel) or allows all (admin).
 */
final class SponsorForms
{
    public static function location(): array
    {
        return [
            TextInput::make('name')->label('نام شعبه')->required()->maxLength(150),
            TextInput::make('city')->label('شهر')->maxLength(64),
            Textarea::make('address')->label('نشانی')->rows(2)->maxLength(255)->columnSpanFull(),
            TextInput::make('latitude')->label('عرض جغرافیایی')->numeric()->required()->minValue(24)->maxValue(40.5)
                ->helperText('از نقشه (مثلاً نشان یا گوگل) با نگه داشتن انگشت روی محل کپی کنید.'),
            TextInput::make('longitude')->label('طول جغرافیایی')->numeric()->required()->minValue(44)->maxValue(63.5),
            TextInput::make('radius_m')->label('شعاع محدوده (متر)')->numeric()->integer()->minValue(20)->maxValue(300)->default(60)->required()
                ->helperText('کوچک‌ترین شعاعی که کل فضای شعبه را بپوشاند. شعاع بزرگ امکان پاداش از بیرون شعبه را می‌دهد.'),
            KeyValue::make('opening_hours_text')->label('ساعات کاری (خالی = همیشه باز)')
                ->keyLabel('روز (sat, sun, mon, tue, wed, thu, fri)')->valueLabel('بازه‌ها، مثل 09:00-13:00, 16:00-22:00')
                ->columnSpanFull(),
        ];
    }

    /** KeyValue text ⇄ opening_hours JSON. */
    public static function hoursToText(?array $hours): array
    {
        return collect($hours ?? [])->map(fn (array $ranges) => collect($ranges)->map(fn ($r) => $r[0].'-'.$r[1])->implode(', '))->all();
    }

    public static function textToHours(?array $text): ?array
    {
        $hours = [];
        foreach ($text ?? [] as $day => $value) {
            $day = strtolower(trim((string) $day));
            if (! in_array($day, Location::DAYS, true)) {
                continue;
            }
            foreach (preg_split('/\s*,\s*/', trim((string) $value)) as $range) {
                if (preg_match('/^(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})$/', $range, $m) && $m[1] < $m[2]) {
                    $hours[$day][] = [$m[1], $m[2]];
                }
            }
        }

        return $hours === [] ? null : $hours;
    }

    /** @param Closure(Builder, ?Campaign): Builder $scope limits branches/coupons to the campaign's sponsor */
    public static function campaign(Closure $scope, bool $withSponsor = false): array
    {
        return [
            ...($withSponsor ? [Select::make('sponsor_id')->label('اسپانسر')->relationship('sponsor', 'name')->required()->searchable()->preload()] : []),
            TextInput::make('name')->label('عنوان')->required()->maxLength(150)->columnSpanFull(),
            Textarea::make('description')->label('توضیح برای کاربر')->rows(3)->columnSpanFull(),
            Select::make('locations')->label('شعبه‌ها')->multiple()->required()->preload()
                ->relationship('locations', 'name', fn (Builder $query, ?Campaign $record) => $scope($query, $record)->whereIn('locations.status', [LocationStatus::Approved, LocationStatus::Pending]))
                ->columnSpanFull(),
            Section::make('روش تأیید حضور')->columns(2)->columnSpanFull()->schema([
                Select::make('verification_method')->label('روش')->required()->default(VerificationMethod::GeofenceQr->value)
                    ->options(collect(VerificationMethod::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()]))
                    ->helperText('QR چرخشی روی صفحه شعبه امن‌ترین روش است. QR چاپی هرگز به‌تنهایی پاداش نمی‌دهد.'),
                TextInput::make('min_stay_seconds')->label('حداقل زمان حضور (ثانیه)')->numeric()->integer()->minValue(60)->maxValue(3600)->default(180)->required(),
            ]),
            Section::make('پاداش و محدودیت')->columns(3)->columnSpanFull()->schema([
                TextInput::make('reward_points')->label('امتیاز هر بازدید')->numeric()->integer()->minValue(0)->maxValue(5000)->default(50)->required(),
                Select::make('coupon_id')->label('کوپن هدیه')->relationship('coupon', 'title', fn (Builder $query, ?Campaign $record) => $scope($query, $record)),
                TextInput::make('point_budget')->label('سقف بودجه کمپین (امتیاز)')->numeric()->integer()->minValue(0)->required(),
                TextInput::make('max_rewards_per_user')->label('حداکثر پاداش هر کاربر')->numeric()->integer()->minValue(1)->maxValue(100)->default(1)->required(),
                TextInput::make('cooldown_hours')->label('فاصله بین دو پاداش در یک شعبه (ساعت)')->numeric()->integer()->minValue(0)->maxValue(720)->default(24)->required(),
                TextInput::make('total_limit')->label('سقف کل پاداش‌ها')->numeric()->integer()->minValue(1),
            ]),
            DateTimePicker::make('starts_at')->label('شروع')->required(),
            DateTimePicker::make('ends_at')->label('پایان')->required()->after('starts_at'),
            FileUpload::make('image_path')->label('تصویر')->image()->disk('public')->directory('campaigns')->maxSize(1024)->columnSpanFull(),
        ];
    }

    public static function coupon(bool $withSponsor = false): array
    {
        return [
            ...($withSponsor ? [Select::make('sponsor_id')->label('اسپانسر (خالی = پلتفرم)')->relationship('sponsor', 'name')->searchable()->preload()] : []),
            TextInput::make('title')->label('عنوان')->required()->maxLength(150)->columnSpanFull(),
            Textarea::make('description')->label('توضیح')->rows(2)->columnSpanFull(),
            Textarea::make('terms')->label('شرایط استفاده')->rows(3)->columnSpanFull(),
            Select::make('discount_type')->label('نوع تخفیف')->required()->live()
                ->options(collect(DiscountType::cases())->mapWithKeys(fn ($d) => [$d->value => $d->label()])),
            TextInput::make('discount_value')->label('مقدار')->numeric()->integer()->minValue(0)->required()
                ->maxValue(fn (Get $get) => $get('discount_type') === DiscountType::Percent->value ? 100 : 1_000_000_000),
            TextInput::make('min_purchase_rial')->label('حداقل خرید (ریال)')->numeric()->integer()->minValue(0),
            TextInput::make('shared_code')->label('کد آنلاین مشترک (اختیاری)')->maxLength(32)->alphaDash(),
            TextInput::make('valid_days')->label('اعتبار پس از دریافت (روز)')->numeric()->integer()->minValue(1)->maxValue(365)->default(30)->required(),
            DateTimePicker::make('expires_at')->label('پایان اعتبار کلی'),
            TextInput::make('usage_limit')->label('تعداد کل')->numeric()->integer()->minValue(1),
            TextInput::make('per_user_limit')->label('برای هر کاربر')->numeric()->integer()->minValue(1)->maxValue(20)->default(1)->required(),
            Toggle::make('claimable')->label('قابل دریافت با امتیاز در اپ')->live(),
            TextInput::make('point_cost')->label('قیمت (امتیاز)')->numeric()->integer()->minValue(0)->maxValue(1_000_000)->default(0)
                ->visible(fn (Get $get) => (bool) $get('claimable')),
            FileUpload::make('image_path')->label('تصویر')->image()->disk('public')->directory('coupons')->maxSize(1024)->columnSpanFull(),
        ];
    }
}
