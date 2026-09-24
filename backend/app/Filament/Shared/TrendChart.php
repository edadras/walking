<?php

namespace App\Filament\Shared;

use App\Domain\Analytics\Metrics;
use Filament\Widgets\ChartWidget;

/** Daily trend chart with a 7/30/90-day filter; subclasses return named series. */
abstract class TrendChart extends ChartWidget
{
    public ?string $filter = '30';

    protected ?string $maxHeight = '260px';

    protected ?string $pollingInterval = null;

    /** Brand palette: green, gold, danger, info. */
    protected const COLORS = ['#1A7F4B', '#E8A400', '#C3362B', '#2563A8'];

    /** @return array<string, array<string, int>> label => [date => value] */
    abstract protected function series(Metrics $metrics, int $days): array;

    protected function getFilters(): ?array
    {
        return ['7' => '۷ روز', '30' => '۳۰ روز', '90' => '۹۰ روز'];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = (int) ($this->filter ?? 30);
        $metrics = app(Metrics::class);
        $datasets = [];
        $i = 0;
        foreach ($this->series($metrics, $days) as $label => $values) {
            $color = self::COLORS[$i++ % count(self::COLORS)];
            $datasets[] = ['label' => $label, 'data' => array_values($values), 'borderColor' => $color, 'backgroundColor' => $color.'33', 'tension' => 0.3, 'pointRadius' => 0, 'fill' => $this->getType() === 'line' && $i === 1];
        }

        return ['datasets' => $datasets, 'labels' => $metrics->labels($days)];
    }
}
