<?php

namespace App\Console\Commands;

use App\Models\Rental;
use App\Notifications\RentPaymentReminder;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendRentPaymentReminders extends Command
{
    protected $signature = 'rent:payment-reminders';
    protected $description = 'Send rent payment reminders to tenants';

    public function handle()
    {
        $today = Carbon::now();

        // Get all active rentals
        $rentals = Rental::where('status', 'active')
            ->with(['tenant', 'property'])
            ->get();

        foreach ($rentals as $rental) {
            // Calculate days until rent is due
            $dueDate = Carbon::create($today->year, $today->month, $rental->payment_due_day);
            
            if ($today->day > $rental->payment_due_day) {
                $dueDate->addMonth();
            }

            $daysUntilDue = $today->diffInDays($dueDate, false);

            // Send reminder 5 days and 1 day before due date
            if ($daysUntilDue === 5 || $daysUntilDue === 1) {
                $rental->tenant->notify(new RentPaymentReminder($rental, $daysUntilDue));
            }
        }

        $this->info('Rent payment reminders sent successfully.');
    }
}