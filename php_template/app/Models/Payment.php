<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_id',
        'amount',
        'payment_date',
        'due_date',
        'status',
        'payment_method',
        'transaction_id',
        'stripe_payment_intent_id',
        'notes'
    ];

    protected $casts = [
        'payment_date' => 'datetime',
        'due_date' => 'date'
    ];

    public function rental()
    {
        return $this->belongsTo(Rental::class);
    }
}