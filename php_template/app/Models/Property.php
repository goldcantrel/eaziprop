<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'landlord_id',
        'name',
        'type',
        'address',
        'city',
        'state',
        'zip_code',
        'country',
        'description',
        'monthly_rent',
        'status',
        'bedrooms',
        'bathrooms',
        'square_feet',
        'available_from',
        'minimum_lease_period'
    ];

    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function rentals()
    {
        return $this->hasMany(Rental::class);
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