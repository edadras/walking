<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('retention:prune')->dailyAt('03:30')->withoutOverlapping()->onOneServer();
Schedule::command('wallet:release-pending')->everyTenMinutes()->withoutOverlapping()->onOneServer();
