<?php

namespace App\Filament\Sponsor\Resources\Visits\Pages;

use App\Domain\Audit\AuditLogger;
use App\Filament\Sponsor\Resources\Visits\VisitResource;
use App\Models\Visit;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVisits extends ListRecords
{
    protected static string $resource = VisitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...(VisitResource::canCreate() ? [CreateAction::make()] : []),
            Action::make('export')->label('خروجی ۹۰ روز (CSV)')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->action(function () {
                    app(AuditLogger::class)->log('sponsor.visits_exported', auth('sponsor')->user()->sponsor);

                    return response()->streamDownload(function () {
                        $out = fopen('php://output', 'w');
                        fwrite($out, "\xEF\xBB\xBF");
                        fputcsv($out, ['زمان', 'کمپین', 'شعبه', 'وضعیت', 'حضور (ثانیه)', 'امتیاز'], escape: '');
                        VisitResource::getEloquentQuery()->where('visits.created_at', '>=', now()->subDays(90))->with(['campaign', 'location'])->orderBy('id')
                            ->chunk(500, function ($chunk) use ($out) {
                                foreach ($chunk as $v) {
                                    /** @var Visit $v */
                                    fputcsv($out, [$v->created_at->setTimezone('Asia/Tehran')->format('Y-m-d H:i'), $v->campaign->name, $v->location->name, $v->status->label(), $v->stay_seconds, $v->points_awarded], escape: '');
                                }
                            });
                        fclose($out);
                    }, 'visits.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
        ];
    }
}
