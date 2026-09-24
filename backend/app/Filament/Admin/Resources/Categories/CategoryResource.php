<?php

namespace App\Filament\Admin\Resources\Categories;

use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CategoryResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = Category::class;

    protected static ?string $viewAbility = 'store.manage';

    protected static ?string $manageAbility = 'store.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'فروشگاه';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'دسته‌بندی';

    protected static ?string $pluralModelLabel = 'دسته‌بندی‌ها';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('نام')->required()->maxLength(100),
            TextInput::make('slug')->label('شناسه (انگلیسی)')->required()->alphaDash()->maxLength(100)->unique(ignoreRecord: true),
            Select::make('parent_id')->label('والد')->relationship('parent', 'name'),
            TextInput::make('icon')->label('آیکون')->maxLength(32),
            TextInput::make('sort')->label('ترتیب')->numeric()->integer()->default(0),
            Toggle::make('is_active')->label('فعال')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort')->columns([
            TextColumn::make('name')->label('نام'),
            TextColumn::make('slug')->label('شناسه'),
            TextColumn::make('parent.name')->label('والد'),
            TextColumn::make('products_count')->label('کالا')->counts('products'),
            IconColumn::make('is_active')->label('فعال')->boolean(),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
