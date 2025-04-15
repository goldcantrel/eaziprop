<?php

namespace App\Models;

use Carbon\Carbon;

class MaintenanceRequest extends SupabaseModel
{
    protected $table = 'maintenance_requests';

    protected $fillable = [
        'property_id',
        'tenant_email',
        'assigned_to_email',
        'title',
        'description',
        'priority',
        'status',
        'photos',
        'estimated_cost',
        'actual_cost',
        'completed_at'
    ];

    protected $casts = [
        'photos' => 'array',
        'estimated_cost' => 'float',
        'actual_cost' => 'float',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the property associated with the maintenance request.
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the chat messages related to this maintenance request.
     */
    public function chatMessages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * Check if the request is pending.
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the request is in progress.
     */
    public function isInProgress()
    {
        return $this->status === 'in_progress';
    }

    /**
     * Check if the request is completed.
     */
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    /**
     * Get the status badge class.
     */
    public function getStatusBadgeClass()
    {
        return [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'in_progress' => 'bg-blue-100 text-blue-800',
            'completed' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-gray-100 text-gray-800'
        ][$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * Get the priority badge class.
     */
    public function getPriorityBadgeClass()
    {
        return [
            'low' => 'bg-green-100 text-green-800',
            'medium' => 'bg-yellow-100 text-yellow-800',
            'high' => 'bg-red-100 text-red-800',
            'emergency' => 'bg-red-600 text-white'
        ][$this->priority] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * Assign the request to a maintenance worker.
     */
    public function assignTo($workerEmail)
    {
        $this->assigned_to_email = $workerEmail;
        $this->status = 'in_progress';
        $this->save();

        // Create a chat message for the assignment
        ChatMessage::create([
            'sender_email' => 'system',
            'receiver_email' => $workerEmail,
            'maintenance_request_id' => $this->id,
            'message' => "You have been assigned to maintenance request: {$this->title}",
            'message_type' => 'system'
        ]);
    }

    /**
     * Mark the request as completed.
     */
    public function complete($actualCost = null)
    {
        $this->status = 'completed';
        $this->completed_at = Carbon::now();
        if ($actualCost !== null) {
            $this->actual_cost = $actualCost;
        }
        $this->save();

        // Notify the tenant
        ChatMessage::create([
            'sender_email' => 'system',
            'receiver_email' => $this->tenant_email,
            'maintenance_request_id' => $this->id,
            'message' => "Your maintenance request '{$this->title}' has been completed.",
            'message_type' => 'system'
        ]);
    }

    /**
     * Get maintenance requests that need attention.
     */
    public static function needsAttention()
    {
        return self::where('status', 'pending')
            ->orWhere(function ($query) {
                $query->where('status', 'in_progress')
                      ->where('updated_at', '<=', Carbon::now()->subDays(3));
            });
    }

    /**
     * Get maintenance statistics.
     */
    public static function getStatistics($propertyId = null)
    {
        $query = self::query();

        if ($propertyId) {
            $query->where('property_id', $propertyId);
        }

        $baseQuery = clone $query;

        return [
            'total' => $query->count(),
            'by_status' => [
                'pending' => $baseQuery->where('status', 'pending')->count(),
                'in_progress' => $baseQuery->where('status', 'in_progress')->count(),
                'completed' => $baseQuery->where('status', 'completed')->count(),
                'cancelled' => $baseQuery->where('status', 'cancelled')->count()
            ],
            'by_priority' => [
                'low' => $baseQuery->where('priority', 'low')->count(),
                'medium' => $baseQuery->where('priority', 'medium')->count(),
                'high' => $baseQuery->where('priority', 'high')->count(),
                'emergency' => $baseQuery->where('priority', 'emergency')->count()
            ],
            'average_completion_time' => $baseQuery
                ->whereNotNull('completed_at')
                ->avg(DB::raw('EXTRACT(EPOCH FROM (completed_at - created_at))/3600')), // in hours
            'total_cost' => $baseQuery->where('status', 'completed')->sum('actual_cost')
        ];
    }
}
