<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_id',
        'tenant_id',
        'amount',
        'payment_method',
        'status',
        'due_date',
        'payment_date',
        'transaction_id',
        'notes'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'payment_date' => 'date'
    ];

    public function rental()
    {
        return $this->belongsTo(Rental::class);
    }

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function isOverdue()
    {
        return $this->status !== 'completed' && $this->due_date < now();
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }
}