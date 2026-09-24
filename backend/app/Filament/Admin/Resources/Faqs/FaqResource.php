<?php

namespace App\Filament\Admin\Resources\Faqs;

use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\Faq;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class FaqResource extends Resource
{
    use RequiresAbility;

    public const CATEGORIES = [
        'general' => 'عمومی',
        'points' => 'امتیاز و پاداش',
        'steps' => 'ثبت قدم',
        'store' => 'فروشگاه',
        'account' => 'حساب کاربری',
    ];

    protected static ?string $model = Faq::class;

    protected static ?string $viewAbility = 'content.manage';

    protected static ?string $manageAbility = 'content.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|UnitEnum|null $navigationGroup = 'محتوا';

    protected static ?string $modelLabel = 'پرسش';

    protected static ?string $pluralModelLabel = 'پرسش‌های متداول';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category')->label('دسته')->options(self::CATEGORIES)->required(),
            TextInput::make('sort')->label('ترتیب')->numeric()->default(0),
            TextInput::make('question')->label('پرسش')->required()->maxLength(255)->columnSpanFull(),
            Textarea::make('answer')->label('پاسخ')->required()->rows(5)->columnSpanFull(),
            Toggle::make('is_published')->label('منتشر شده')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('question')->label('پرسش')->limit(60)->searchable(),
                TextColumn::make('category')->label('دسته')->formatStateUsing(fn (string $state) => self::CATEGORIES[$state] ?? $state),
                IconColumn::make('is_published')->label('منتشر')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFaqs::route('/'),
            'create' => Pages\CreateFaq::route('/create'),
            'edit' => Pages\EditFaq::route('/{record}/edit'),
        ];
    }
}
