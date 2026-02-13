<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;


Schedule::command('app:process-current-day')
	->dailyAt('00:00')
	->appendOutputTo(storage_path('logs/cron.log')); // Run daily at 00:01 AM

Schedule::command('app:create-next-day')
	->dailyAt('00:01')
	->appendOutputTo(storage_path('logs/cron.log'));

Schedule::command('app:process-transfers')
	->dailyAt('00:10')
	->appendOutputTo(storage_path('logs/cron.log')); // Run daily at 00:10 AM
