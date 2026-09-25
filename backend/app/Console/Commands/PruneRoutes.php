<?php

namespace App\Console\Commands;

use App\Domain\Map\RouteMap;
use Illuminate\Console\Command;

class PruneRoutes extends Command
{
    protected $signature = 'routes:prune';

    protected $description = 'Delete public-map lines 24 h after their last point';

    public function handle(RouteMap $map): int
    {
        $this->info('Deleted '.$map->prune().' route lines.');

        return self::SUCCESS;
    }
}
