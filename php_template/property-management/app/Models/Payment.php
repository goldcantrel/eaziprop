<?php

namespace App\Models;

use Carbon\Carbon;

class Payment extends SupabaseModel
{
    protected $table = 'payments';

    protected $fillable = [
        'rental_id',
        'amount',
        'payment_method',
        'payment_date',
        'due_date',
        'status',
        'transaction_id',
        'notes'
    ];

    protected $casts = [
        'amount' => 'float',
        'payment_date' => 'date',
        'due_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the rental that owns the payment.
     */
    public function rental()
    {
        return $this->belongsTo(Rental::class);
    }

    /**
     * Get payment status badge class.
     */
    public function getStatusBadgeClass()
    {
        return [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'completed' => 'bg-green-100 text-green-800',
            'failed' => 'bg-red-100 text-red-800',
            'overdue' => 'bg-red-100 text-red-800',
            'refunded' => 'bg-gray-100 text-gray-800',
        ][$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * Check if payment is overdue.
     */
    public function isOverdue()
    {
        return $this->status === 'pending' && Carbon::now()->greaterThan($this->due_date);
    }

    /**
     * Mark payment as completed.
     */
    public function markAsCompleted($transaction_id = null)
    {
        $this->status = 'completed';
        $this->payment_date = Carbon::now();
        if ($transaction_id) {
            $this->transaction_id = $transaction_id;
        }
        $this->save();

        // Update rental status if needed
        $rental = $this->rental;
        if ($rental && $rental->status === 'pending_payment') {
            $rental->status = 'active';
            $rental->save();
        }
    }

    /**
     * Mark payment as failed.
     */
    public function markAsFailed($notes = null)
    {
        $this->status = 'failed';
        if ($notes) {
            $this->notes = $notes;
        }
        $this->save();
    }

    /**
     * Process refund.
     */
    public function processRefund($notes = null)
    {
        if ($this->status !== 'completed') {
            throw new \Exception('Only completed payments can be refunded.');
        }

        $this->status = 'refunded';
        if ($notes) {
            $this->notes = $notes;
        }
        $this->save();
    }

    /**
     * Get pending payments that need reminders.
     */
    public static function needsReminder()
    {
        return self::where('status', 'pending')
            ->where('due_date', '<=', Carbon::now()->addDays(5));
    }

    /**
     * Get overdue payments.
     */
    public static function overdue()
    {
        return self::where('status', 'pending')
            ->where('due_date', '<', Carbon::now());
    }

    /**
     * Get payment statistics for a given period.
     */
    public static function getStatistics($startDate = null, $endDate = null)
    {
        $query = self::query();

        if ($startDate) {
            $query->where('payment_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('payment_date', '<=', $endDate);
        }

        return [
            'total_received' => $query->where('status', 'completed')->sum('amount'),
            'total_pending' => $query->where('status', 'pending')->sum('amount'),
            'total_overdue' => $query->where('status', 'overdue')->sum('amount'),
            'total_refunded' => $query->where('status', 'refunded')->sum('amount'),
            'payment_methods' => $query->where('status', 'completed')
                ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
                ->groupBy('payment_method')
                ->get()
        ];
    }
}
