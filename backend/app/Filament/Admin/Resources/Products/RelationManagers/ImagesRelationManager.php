<?php

namespace App\Filament\Admin\Resources\Products\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'تصاویر';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            // Cards and the product gallery render at ~6:5; crop and downscale in the browser so
            // phones don't pull multi-megabyte originals.
            FileUpload::make('path')->label('تصویر')->image()->disk('public')->directory('products')->visibility('public')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(2048)
                ->imageEditor()->imageCropAspectRatio('6:5')->imageResizeMode('cover')
                ->imageResizeTargetWidth('1200')->imageResizeTargetHeight('1000')
                ->helperText('نسبت ۶:۵، حداقل ۶۰۰×۵۰۰ پیکسل؛ اولین تصویر (کمترین ترتیب) تصویر اصلی کالاست.')
                ->required(),
            TextInput::make('sort')->label('ترتیب')->numeric()->integer()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->reorderable('sort')->columns([
            ImageColumn::make('path')->label('تصویر')->disk('public'),
            TextColumn::make('sort')->label('ترتیب'),
        ])->headerActions([CreateAction::make()])->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
