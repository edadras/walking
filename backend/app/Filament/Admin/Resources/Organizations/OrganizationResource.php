<?php

namespace App\Filament\Admin\Resources\Organizations;

use App\Domain\Audit\AuditLogger;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\Organization;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** B2B customers. Sales creates the organization and its first HR admin; the company pays seats online or by invoice. */
class OrganizationResource extends \Filament\Resources\Resource
{
    use RequiresAbility;

    protected static ?string $model = Organization::class;

    protected static ?string $viewAbility = 'organizations.manage';

    protected static ?string $manageAbility = 'organizations.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'اسپانسرها';

    protected static ?string $modelLabel = 'سازمان';

    protected static ?string $pluralModelLabel = 'سازمان‌ها (B2B)';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('نام سازمان')->required()->maxLength(120),
            TextInput::make('contact_name')->label('رابط')->maxLength(120),
            TextInput::make('contact_phone')->label('تلفن رابط')->maxLength(20),
            TextInput::make('seats')->label('صندلی')->numeric()->integer()->minValue(1)->required()->default(50),
            TextInput::make('seat_price_rial')->label('قیمت هر صندلی در ماه (ریال)')->numeric()->integer()->minValue(0)->required()->default(1_500_000),
            DatePicker::make('paid_until')->label('اعتبار تا')->helperText('برای پرداخت خارج از درگاه (فاکتور رسمی) اینجا تمدید کنید.'),
            TagsInput::make('departments')->label('واحدها')->columnSpanFull(),
            Section::make('مدیر منابع انسانی (ورود به پنل سازمان)')->visibleOn('create')->columns(3)->columnSpanFull()->schema([
                TextInput::make('owner_name')->label('نام')->required(),
                TextInput::make('owner_email')->label('ایمیل')->email()->required()->unique('organization_users', 'email'),
                TextInput::make('owner_password')->label('رمز موقت')->password()->required()->minLength(12),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')->label('نام')->searchable(),
                TextColumn::make('members_count')->label('اعضا')->counts('members'),
                TextColumn::make('seats')->label('صندلی')->numeric(),
                TextColumn::make('paid_until')->label('اعتبار تا')->date()->placeholder('—')
                    ->color(fn (Organization $r) => $r->isActive() ? 'success' : 'danger'),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (string $state) => $state === 'active' ? 'فعال' : 'معلق')
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'danger'),
                TextColumn::make('join_code')->label('کد عضویت')->copyable(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('suspend')->label(fn (Organization $r) => $r->status === 'active' ? 'تعلیق' : 'فعال‌سازی')->color(fn (Organization $r) => $r->status === 'active' ? 'danger' : 'success')
                    ->visible(fn () => static::allows('organizations.manage'))
                    ->schema(fn (Organization $r) => $r->status === 'active' ? [Textarea::make('reason')->label('دلیل')->required()] : [])
                    ->action(function (Organization $record, array $data) {
                        $record->update(['status' => $record->status === 'active' ? 'suspended' : 'active']);
                        app(AuditLogger::class)->log('organization.'.$record->status, $record, meta: ['reason' => $data['reason'] ?? null]);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrganizations::route('/'),
            'create' => Pages\CreateOrganization::route('/create'),
            'edit' => Pages\EditOrganization::route('/{record}/edit'),
        ];
    }
}
