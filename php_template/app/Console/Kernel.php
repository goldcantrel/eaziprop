<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        //
    ];

    protected function schedule(Schedule $schedule)
    {
        // Generate rent payment reminders
        $schedule->command('rent:payment-reminders')
            ->daily()
            ->at('09:00');

        // Check for overdue payments
        $schedule->command('rent:check-overdue')
            ->daily()
            ->at('10:00');

        // Clean up expired temporary files
        $schedule->command('storage:clean-temp')
            ->daily()
            ->at('02:00');

        // Backup database
        $schedule->command('backup:run')
            ->daily()
            ->at('01:00');

        // Send maintenance request follow-ups
        $schedule->command('maintenance:follow-up')
            ->dailyAt('11:00');
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}