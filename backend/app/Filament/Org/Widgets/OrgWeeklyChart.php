<?php

namespace App\Filament\Org\Widgets;

use App\Domain\Organization\OrganizationService;
use App\Models\OrganizationUser;
use App\Support\Jalali;
use Filament\Widgets\ChartWidget;

class OrgWeeklyChart extends ChartWidget
{
    protected ?string $heading = 'روند میانگین قدم روزانه (۸ هفته)';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $user = auth('org')->user();
        $weeks = $user instanceof OrganizationUser ? app(OrganizationService::class)->stats($user->organization)['weekly_avg_daily_steps'] : [];

        return [
            'datasets' => [['label' => 'میانگین قدم روزانه هر عضو', 'data' => array_values($weeks), 'borderColor' => '#1A7F4B', 'backgroundColor' => 'rgba(26,127,75,.15)', 'fill' => true]],
            'labels' => array_map(fn ($d) => Jalali::format(new \DateTimeImmutable($d)), array_keys($weeks)),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
