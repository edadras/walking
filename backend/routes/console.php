<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('retention:prune')->dailyAt('03:30')->withoutOverlapping()->onOneServer();
Schedule::command('wallet:release-pending')->everyTenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('challenges:close')->hourly()->onOneServer();
Schedule::command('leaderboard:snapshot')->dailyAt('00:10')->timezone('Asia/Tehran')->onOneServer();
Schedule::command('notifications:streak-warnings')->dailyAt('20:00')->timezone('Asia/Tehran')->onOneServer();
Schedule::command('sponsors:housekeeping')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
