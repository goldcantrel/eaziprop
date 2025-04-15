<?php

namespace App\Models;

class Property extends SupabaseModel
{
    protected $table = 'properties';

    protected $fillable = [
        'owner_email',
        'title',
        'description',
        'address',
        'type',
        'price',
        'bedrooms',
        'bathrooms',
        'square_feet',
        'available_from',
        'status',
        'amenities'
    ];

    protected $casts = [
        'price' => 'float',
        'bedrooms' => 'integer',
        'bathrooms' => 'float',
        'square_feet' => 'float',
        'available_from' => 'date',
        'amenities' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the rentals for the property.
     */
    public function rentals()
    {
        return $this->hasMany(Rental::class);
    }

    /**
     * Get the maintenance requests for the property.
     */
    public function maintenanceRequests()
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    /**
     * Get the documents for the property.
     */
    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Get the active rental for the property.
     */
    public function activeRental()
    {
        return $this->rentals()->where('status', 'active')->first();
    }

    /**
     * Get the active tenant for the property.
     */
    public function activeTenant()
    {
        $activeRental = $this->activeRental();
        return $activeRental ? $activeRental->tenant : null;
    }

    /**
     * Scope a query to only include available properties.
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * Check if the property is available for rent.
     */
    public function isAvailable()
    {
        return $this->status === 'available';
    }

    /**
     * Check if the user is the owner of the property.
     */
    public function isOwnedBy($userEmail)
    {
        return $this->owner_email === $userEmail;
    }

    /**
     * Get the property statistics.
     */
    public function getStatistics()
    {
        return [
            'total_rentals' => $this->rentals()->count(),
            'maintenance_requests' => [
                'total' => $this->maintenanceRequests()->count(),
                'pending' => $this->maintenanceRequests()->where('status', 'pending')->count(),
                'in_progress' => $this->maintenanceRequests()->where('status', 'in_progress')->count(),
                'completed' => $this->maintenanceRequests()->where('status', 'completed')->count(),
            ],
            'documents' => $this->documents()->count(),
            'total_revenue' => $this->rentals()->sum('rent_amount')
        ];
    }

    /**
     * Get property search results.
     */
    public static function search(array $filters)
    {
        $query = self::query();

        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if (isset($filters['bedrooms'])) {
            $query->where('bedrooms', '>=', $filters['bedrooms']);
        }

        if (isset($filters['bathrooms'])) {
            $query->where('bathrooms', '>=', $filters['bathrooms']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['available_from'])) {
            $query->where('available_from', '<=', $filters['available_from']);
        }

        if (isset($filters['location'])) {
            $query->where('address', 'ilike', '%' . $filters['location'] . '%');
        }

        return $query;
    }
}
