<?php

namespace App\Filament\Admin\Pages;

use App\Domain\Analytics\ReportExporter;
use App\Domain\Audit\AuditLogger;
use App\Models\Admin;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class Reports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'گزارش‌ها';

    protected static ?string $navigationLabel = 'خروجی گزارش';

    protected static ?string $title = 'خروجی گزارش (CSV)';

    protected static ?string $slug = 'reports';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof Admin && $admin->hasAbility('reports.view');
    }

    public function mount(): void
    {
        $this->form->fill(['report' => 'daily', 'from' => now('Asia/Tehran')->subDays(29)->toDateString(), 'to' => now('Asia/Tehran')->toDateString()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->columns(3)->components([
            Select::make('report')->label('گزارش')->required()->options(ReportExporter::REPORTS),
            DatePicker::make('from')->label('از')->required(),
            DatePicker::make('to')->label('تا')->required()->afterOrEqual('from')
                ->rule(fn ($get) => function ($attribute, $value, $fail) use ($get) {
                    if ($get('from') && CarbonImmutable::parse($get('from'))->diffInDays(CarbonImmutable::parse($value)) > 366) {
                        $fail('بازه گزارش حداکثر یک سال است.');
                    }
                }),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('export')
                ->footer([Actions::make([Action::make('export')->label('دانلود CSV')->icon('heroicon-o-arrow-down-tray')->submit('export')])]),
        ]);
    }

    public function export(): StreamedResponse
    {
        $state = $this->form->getState();
        $from = CarbonImmutable::parse($state['from'], 'Asia/Tehran')->startOfDay();
        $to = CarbonImmutable::parse($state['to'], 'Asia/Tehran')->endOfDay();
        $report = $state['report'];

        app(AuditLogger::class)->log('report.exported', null, new: ['report' => $report, 'from' => $state['from'], 'to' => $state['to']]);

        return response()->streamDownload(
            fn () => app(ReportExporter::class)->stream($report, $from, $to),
            "gamyar-{$report}-{$state['from']}-{$state['to']}.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
