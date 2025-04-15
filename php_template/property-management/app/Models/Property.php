<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'landlord_id',
        'address',
        'type',
        'bedrooms',
        'bathrooms',
        'square_feet',
        'rent_amount',
        'description',
        'amenities',
        'is_available',
        'status'
    ];

    protected $casts = [
        'amenities' => 'array',
        'is_available' => 'boolean',
        'rent_amount' => 'decimal:2',
        'square_feet' => 'decimal:2'
    ];

    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function rentals()
    {
        return $this->hasMany(Rental::class);
    }

    public function currentRental()
    {
        return $this->hasOne(Rental::class)->where('status', 'active');
    }

    public function maintenanceRequests()
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function chatMessages()
    {
        return $this->hasMany(ChatMessage::class);
    }
}