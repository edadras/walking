<?php

namespace App\Filament\Admin\Resources\Products;

use App\Enums\ProductType;
use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ProductResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = Product::class;

    protected static ?string $viewAbility = 'store.manage';

    protected static ?string $manageAbility = 'store.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'فروشگاه';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'کالا';

    protected static ?string $pluralModelLabel = 'کالاها';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('نام')->required()->maxLength(150),
            TextInput::make('slug')->label('شناسه (انگلیسی)')->required()->alphaDash()->maxLength(160)->unique(ignoreRecord: true),
            Select::make('category_id')->label('دسته‌بندی')->relationship('category', 'name')->required(),
            Select::make('type')->label('نوع')->required()->live()->disabledOn('edit')
                ->options(collect(ProductType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()]))
                ->helperText('کد دیجیتال: موجودی = تعداد کدهای واردشده. کوپن: یک کوپن اسپانسر صادر می‌شود.'),
            Select::make('coupon_id')->label('کوپن')->relationship('coupon', 'title')->searchable()->preload()
                ->visible(fn (Get $get) => $get('type') === ProductType::Coupon->value)->required(fn (Get $get) => $get('type') === ProductType::Coupon->value),
            Select::make('sponsor_id')->label('اسپانسر (اختیاری)')->relationship('sponsor', 'name'),
            TextInput::make('summary')->label('خلاصه')->maxLength(250)->columnSpanFull(),
            RichEditor::make('description')->label('توضیحات')->columnSpanFull()->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList', 'link']),
            Section::make('قیمت و موجودی')->columns(3)->columnSpanFull()->schema([
                TextInput::make('point_price')->label('قیمت (امتیاز)')->numeric()->integer()->minValue(1)->required(),
                TextInput::make('rial_price')->label('قیمت ریالی (فقط با پرداخت ریالی)')->numeric()->integer()->minValue(0),
                TextInput::make('stock')->label('موجودی (خالی = نامحدود)')->numeric()->integer()->minValue(0)
                    ->disabled(fn (Get $get) => $get('type') === ProductType::DigitalCode->value)->dehydrated(fn (Get $get) => $get('type') !== ProductType::DigitalCode->value),
                TextInput::make('max_per_user')->label('سقف خرید هر نفر')->numeric()->integer()->minValue(1),
                TextInput::make('min_level')->label('حداقل سطح')->numeric()->integer()->minValue(1)->default(1),
                TextInput::make('sort')->label('ترتیب')->numeric()->integer()->default(0),
            ]),
            Toggle::make('is_active')->label('نمایش در فروشگاه'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->label('نام')->searchable(),
                TextColumn::make('category.name')->label('دسته'),
                TextColumn::make('type')->label('نوع')->badge()->formatStateUsing(fn (ProductType $state) => $state->label()),
                TextColumn::make('point_price')->label('قیمت')->numeric(),
                TextColumn::make('stock')->label('موجودی')->placeholder('نامحدود')
                    ->color(fn (?int $state) => $state !== null && $state <= 5 ? 'danger' : null),
                TextColumn::make('sold_count')->label('فروش')->numeric(),
                IconColumn::make('is_active')->label('فعال')->boolean(),
            ])
            ->filters([SelectFilter::make('type')->label('نوع')->options(collect(ProductType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()]))])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\ImagesRelationManager::class, RelationManagers\CodesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
