<?php

namespace App\Filament\Shared;

use App\Enums\AdFormat;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Ad campaign + creative forms shared by the admin and sponsor panels. */
final class AdForms
{
    public static function campaign(bool $withSponsor = false, bool $withPriority = false): array
    {
        return [
            ...($withSponsor ? [Select::make('sponsor_id')->label('تبلیغ‌دهنده (خالی = پلتفرم)')->relationship('sponsor', 'name')->searchable()->preload()] : []),
            TextInput::make('name')->label('نام کمپین')->required()->maxLength(150)->columnSpanFull(),
            Select::make('placements')->label('جایگاه‌ها')->multiple()->preload()->required()->relationship('placements', 'name')->columnSpanFull(),
            DateTimePicker::make('starts_at')->label('شروع')->required(),
            DateTimePicker::make('ends_at')->label('پایان')->required()->after('starts_at'),
            ...($withPriority ? [TextInput::make('priority')->label('اولویت (وزن نمایش)')->numeric()->integer()->minValue(1)->maxValue(100)->default(10)->required()] : []),
            Section::make('محدودیت و هدف‌گیری')->columns(3)->columnSpanFull()->schema([
                TextInput::make('impression_limit')->label('سقف نمایش')->numeric()->integer()->minValue(1),
                TextInput::make('click_limit')->label('سقف کلیک')->numeric()->integer()->minValue(1),
                TextInput::make('frequency_cap_per_day')->label('نمایش به هر کاربر در روز')->numeric()->integer()->minValue(1)->maxValue(50),
                TextInput::make('targeting.min_level')->label('حداقل سطح کاربر')->numeric()->integer()->minValue(1),
                TextInput::make('targeting.max_level')->label('حداکثر سطح کاربر')->numeric()->integer()->minValue(1),
            ]),
            Section::make('تبلیغ جایزه‌دار')->columns(2)->columnSpanFull()->description('فقط برای جایگاه‌های جایزه‌دار. پاداش پس از تأیید زمان تماشا توسط سرور یا تأیید امضاشده شبکه تبلیغاتی داده می‌شود.')->schema([
                TextInput::make('reward_points')->label('امتیاز هر تماشا')->numeric()->integer()->minValue(0)->maxValue(100)->default(0),
                TextInput::make('point_budget')->label('بودجه امتیاز')->numeric()->integer()->minValue(0)->default(0),
            ]),
        ];
    }

    /** Creatives table/form for a relation manager. */
    public static function creativesForm(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('format')->label('قالب')->required()->options(collect(AdFormat::cases())->mapWithKeys(fn ($f) => [$f->value => $f->label()])),
            TextInput::make('title')->label('عنوان')->required()->maxLength(90),
            TextInput::make('body')->label('متن')->maxLength(250)->columnSpanFull(),
            TextInput::make('cta_label')->label('متن دکمه')->maxLength(32),
            TextInput::make('action_url')->label('لینک (https یا مسیر داخل اپ مثل /challenges)')->maxLength(500)
                ->rule('regex:#^(https://|/)[^\s]*$#'),
            TextInput::make('min_view_seconds')->label('حداقل زمان تماشا (ثانیه، جایزه‌دار)')->numeric()->integer()->minValue(5)->maxValue(60)->default(15),
            Toggle::make('is_active')->label('فعال')->default(true),
            FileUpload::make('image_path')->label('تصویر')->image()->disk('public')->directory('ads')->maxSize(1024)->columnSpanFull(),
        ]);
    }

    public static function creativesTable(Table $table, ?Closure $canManage = null): Table
    {
        $canManage ??= fn () => true;

        return $table
            ->columns([
                TextColumn::make('title')->label('عنوان'),
                TextColumn::make('format')->label('قالب')->badge()->formatStateUsing(fn (AdFormat $state) => $state->label()),
                TextColumn::make('action_url')->label('لینک')->limit(40),
                IconColumn::make('is_active')->label('فعال')->boolean(),
            ])
            ->headerActions([CreateAction::make()->visible($canManage)])
            ->recordActions([EditAction::make()->visible($canManage)]);
    }
}
