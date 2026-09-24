<?php

namespace App\Filament\Admin\Resources\CmsPages;

use App\Filament\Admin\Concerns\RequiresAbility;
use App\Models\CmsPage;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CmsPageResource extends Resource
{
    use RequiresAbility;

    protected static ?string $model = CmsPage::class;

    protected static ?string $viewAbility = 'content.manage';

    protected static ?string $manageAbility = 'content.manage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'محتوا';

    protected static ?string $modelLabel = 'صفحه';

    protected static ?string $pluralModelLabel = 'صفحات';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('عنوان')->required()->maxLength(150),
            TextInput::make('slug')->label('نامک')->required()->alphaDash()->maxLength(64)->unique(ignoreRecord: true),
            MarkdownEditor::make('body')->label('متن')->required()->columnSpanFull(),
            Toggle::make('is_published')->label('منتشر شده')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('عنوان')->searchable(),
                TextColumn::make('slug')->label('نامک')->fontFamily('mono'),
                IconColumn::make('is_published')->label('منتشر')->boolean(),
                TextColumn::make('updated_at')->label('به‌روزرسانی')->since(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCmsPages::route('/'),
            'create' => Pages\CreateCmsPage::route('/create'),
            'edit' => Pages\EditCmsPage::route('/{record}/edit'),
        ];
    }
}
