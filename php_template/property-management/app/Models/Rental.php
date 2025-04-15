<?php

namespace App\Models;

use Carbon\Carbon;

class Rental extends SupabaseModel
{
    protected $table = 'rentals';

    protected $fillable = [
        'property_id',
        'tenant_email',
        'start_date',
        'end_date',
        'rent_amount',
        'deposit_amount',
        'payment_day',
        'payment_frequency',
        'status'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'rent_amount' => 'float',
        'deposit_amount' => 'float',
        'payment_day' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the property associated with the rental.
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the payments for the rental.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Check if the rental is active.
     */
    public function isActive()
    {
        return $this->status === 'active';
    }

    /**
     * Check if the rental is expired.
     */
    public function isExpired()
    {
        return Carbon::now()->greaterThan($this->end_date);
    }

    /**
     * Get the next payment date.
     */
    public function getNextPaymentDate()
    {
        $today = Carbon::today();
        $paymentDay = $this->payment_day;
        
        $nextDate = Carbon::today()->day($paymentDay);
        if ($nextDate->isPast()) {
            $nextDate = $nextDate->addMonth();
        }

        return $nextDate;
    }

    /**
     * Get payment statistics.
     */
    public function getPaymentStatistics()
    {
        $payments = $this->payments();
        
        return [
            'total_paid' => $payments->where('status', 'completed')->sum('amount'),
            'total_pending' => $payments->where('status', 'pending')->sum('amount'),
            'total_overdue' => $payments->where('status', 'overdue')->sum('amount'),
            'last_payment' => $payments->where('status', 'completed')
                                      ->orderBy('payment_date', 'desc')
                                      ->first(),
            'next_payment_date' => $this->getNextPaymentDate(),
            'next_payment_amount' => $this->rent_amount
        ];
    }

    /**
     * Get all active rentals.
     */
    public static function active()
    {
        return self::where('status', 'active');
    }

    /**
     * Get rentals that need payment reminders.
     */
    public static function needsPaymentReminder()
    {
        $today = Carbon::today();
        
        return self::active()
            ->whereDoesntHave('payments', function ($query) use ($today) {
                $query->where('due_date', '>=', $today)
                      ->where('status', 'completed');
            })
            ->where('payment_day', '<=', $today->addDays(5)->day);
    }

    /**
     * Get overdue rentals.
     */
    public static function overdue()
    {
        return self::active()
            ->whereHas('payments', function ($query) {
                $query->where('status', 'overdue');
            });
    }
}
