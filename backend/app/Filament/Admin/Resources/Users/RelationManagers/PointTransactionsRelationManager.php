<?php

namespace App\Filament\Admin\Resources\Users\RelationManagers;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Admin;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PointTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'pointTransactions';

    protected static ?string $title = 'تراکنش‌های امتیاز';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof Admin && ($admin->hasAbility('wallet.view') || $admin->hasAbility('fraud.manage'));
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('زمان')->dateTime(),
                TextColumn::make('type')->label('نوع')->formatStateUsing(fn (TransactionType $state) => $state->label()),
                TextColumn::make('amount')->label('مقدار')->numeric()->color(fn (int $state) => $state < 0 ? 'danger' : 'success'),
                TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (TransactionStatus $state) => $state->label()),
                TextColumn::make('balance_after')->label('موجودی بعد')->numeric()->placeholder('—'),
                TextColumn::make('description')->label('شرح')->limit(40),
                TextColumn::make('reason')->label('دلیل')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')->label('نوع')->options(collect(TransactionType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])),
                SelectFilter::make('status')->label('وضعیت')->options(collect(TransactionStatus::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])),
            ]);
    }
}
