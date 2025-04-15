<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\Rental;
use App\Notifications\OverduePaymentNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckOverduePayments extends Command
{
    protected $signature = 'rent:check-overdue';
    protected $description = 'Check for overdue rent payments and send notifications';

    public function handle()
    {
        $today = Carbon::now();

        // Get all active rentals with pending payments
        $rentals = Rental::where('status', 'active')
            ->with(['tenant', 'property', 'payments'])
            ->get();

        foreach ($rentals as $rental) {
            $latestPayment = $rental->payments()
                ->where('status', 'completed')
                ->latest()
                ->first();

            // Calculate last payment date
            $lastPaymentDate = $latestPayment 
                ? Carbon::parse($latestPayment->payment_date)
                : Carbon::parse($rental->start_date);

            // Check if payment is overdue (more than 5 days late)
            if ($today->diffInDays($lastPaymentDate) > 35) { // 30 days + 5 days grace period
                // Create overdue payment record if not exists
                $overduePayment = Payment::firstOrCreate([
                    'rental_id' => $rental->id,
                    'due_date' => $lastPaymentDate->copy()->addMonth(),
                    'amount' => $rental->monthly_rent,
                    'status' => 'pending'
                ]);

                // Notify tenant and landlord
                $rental->tenant->notify(new OverduePaymentNotification($overduePayment));
                $rental->property->landlord->notify(new OverduePaymentNotification($overduePayment));
            }
        }

        $this->info('Overdue payment checks completed successfully.');
    }
}