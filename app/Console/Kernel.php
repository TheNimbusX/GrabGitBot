<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('fitbot:morning-motivation')->dailyAt('10:00');
        $schedule->command('fitbot:churn-reminder')->dailyAt('14:00');
        $schedule->command('fitbot:evening-reminder')->dailyAt('20:00');
        $schedule->command('fitbot:evening-reminder --follow-up')->dailyAt('20:10');
        $schedule->command('fitbot:weekly-focus-reminder')->weeklyOn(1, '9:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
