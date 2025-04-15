<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'tenant_id',
        'title',
        'description',
        'priority',
        'status',
        'notes',
        'assigned_to',
        'estimated_cost',
        'actual_cost',
        'completed_at'
    ];

    protected $casts = [
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'completed_at' => 'datetime'
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function chatMessages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isEmergency()
    {
        return $this->priority === 'emergency';
    }
}