<?php

namespace App\Filament\Admin\Resources\Products\RelationManagers;

use App\Domain\Audit\AuditLogger;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCode;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Code pool of a digital product. Codes are imported but never displayed here. */
class CodesRelationManager extends RelationManager
{
    protected static string $relationship = 'codes';

    protected static ?string $title = 'کدهای دیجیتال';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Product && $ownerRecord->type === ProductType::DigitalCode;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('masked')->label('کد')->state(fn (ProductCode $r) => '••••'.mb_substr($r->code, -4)),
                TextColumn::make('assigned_at')->label('فروخته‌شده')->since()->placeholder('موجود'),
                TextColumn::make('expires_at')->label('انقضا')->date(),
                TextColumn::make('created_at')->label('ورود')->since(),
            ])
            ->filters([TernaryFilter::make('sold')->label('فروخته‌شده')->nullable()->attribute('order_item_id')])
            ->headerActions([
                Action::make('import')->label('ورود کد')->icon('heroicon-o-arrow-up-tray')
                    ->schema([
                        Textarea::make('codes')->label('هر خط یک کد')->rows(10)->required(),
                        DateTimePicker::make('expires_at')->label('انقضا (اختیاری)'),
                    ])
                    ->action(function (array $data) {
                        /** @var Product $product */
                        $product = $this->getOwnerRecord();
                        $lines = collect(preg_split('/\R/', $data['codes']))->map(fn ($c) => trim($c))->filter()->unique()->values();
                        $added = 0;
                        DB::transaction(function () use ($lines, $product, $data, &$added) {
                            foreach ($lines as $code) {
                                $hash = ProductCode::hashOf($code);
                                if (ProductCode::query()->where('code_hash', $hash)->exists()) {
                                    continue;
                                }
                                ProductCode::query()->create(['product_id' => $product->id, 'code' => $code, 'code_hash' => $hash, 'expires_at' => $data['expires_at'] ?? null]);
                                $added++;
                            }
                            $product->syncCodeStock();
                        });
                        app(AuditLogger::class)->log('product.codes_imported', $product, meta: ['added' => $added, 'submitted' => $lines->count()]);
                        Notification::make()->title("{$added} کد اضافه شد".($added < $lines->count() ? ' (تکراری‌ها نادیده گرفته شد)' : ''))->success()->send();
                    }),
            ]);
    }
}
