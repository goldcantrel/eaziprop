<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OverduePaymentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $payment;

    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $amount = number_format($this->payment->amount, 2);
        $dueDate = $this->payment->due_date->format('F j, Y');
        $daysOverdue = $this->payment->due_date->diffInDays(now());
        $property = $this->payment->rental->property;

        $message = (new MailMessage)
            ->subject('OVERDUE: Rent Payment')
            ->greeting("Hello {$notifiable->name},")
            ->line("Your rent payment of \${$amount} was due on {$dueDate} ({$daysOverdue} days ago).")
            ->line("Property: {$property->name}")
            ->line("Address: {$property->address}");

        if ($notifiable->role === 'tenant') {
            $message->line('Please make your payment as soon as possible to avoid any late fees.')
                ->action('Make Payment Now', url('/payments/create'));
        } else {
            $message->line('The tenant has been notified of this overdue payment.')
                ->action('View Payment Details', url('/payments/' . $this->payment->id));
        }

        return $message->line('Thank you for your attention to this matter.');
    }

    public function toArray($notifiable)
    {
        return [
            'payment_id' => $this->payment->id,
            'rental_id' => $this->payment->rental_id,
            'amount' => $this->payment->amount,
            'due_date' => $this->payment->due_date->toDateString(),
            'days_overdue' => $this->payment->due_date->diffInDays(now()),
        ];
    }
}