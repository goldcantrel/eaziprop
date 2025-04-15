<?php

namespace App\Notifications;

use App\Models\Rental;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RentPaymentReminder extends Notification implements ShouldQueue
{
    use Queueable;

    protected $rental;
    protected $daysUntilDue;

    public function __construct(Rental $rental, int $daysUntilDue)
    {
        $this->rental = $rental;
        $this->daysUntilDue = $daysUntilDue;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $amount = number_format($this->rental->monthly_rent, 2);
        $dueDate = now()->addDays($this->daysUntilDue)->format('F j, Y');

        return (new MailMessage)
            ->subject("Rent Payment Reminder - Due in {$this->daysUntilDue} days")
            ->greeting("Hello {$notifiable->name},")
            ->line("This is a reminder that your rent payment of \${$amount} is due on {$dueDate}.")
            ->line("Property: {$this->rental->property->name}")
            ->line("Address: {$this->rental->property->address}")
            ->action('Make Payment', url('/payments/create'))
            ->line('Thank you for using our property management system!');
    }

    public function toArray($notifiable)
    {
        return [
            'rental_id' => $this->rental->id,
            'property_id' => $this->rental->property_id,
            'amount' => $this->rental->monthly_rent,
            'days_until_due' => $this->daysUntilDue,
            'due_date' => now()->addDays($this->daysUntilDue)->toDateString(),
        ];
    }
}