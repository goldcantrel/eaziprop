<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rental extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'tenant_id',
        'lease_start_date',
        'lease_end_date',
        'monthly_rent',
        'security_deposit',
        'lease_terms',
        'status',
        'notes'
    ];

    protected $casts = [
        'lease_start_date' => 'date',
        'lease_end_date' => 'date',
        'monthly_rent' => 'decimal:2',
        'security_deposit' => 'decimal:2'
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function isActive()
    {
        return $this->status === 'active';
    }

    public function isExpired()
    {
        return $this->lease_end_date < now();
    }
}