<?php

namespace App\Models;

use Carbon\Carbon;

class ChatMessage extends SupabaseModel
{
    protected $table = 'chat_messages';

    protected $fillable = [
        'sender_email',
        'receiver_email',
        'property_id',
        'maintenance_request_id',
        'message',
        'message_type',
        'file_path',
        'is_read'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the property associated with the message.
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the maintenance request associated with the message.
     */
    public function maintenanceRequest()
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    /**
     * Mark the message as read.
     */
    public function markAsRead()
    {
        if (!$this->is_read) {
            $this->is_read = true;
            $this->save();
        }
    }

    /**
     * Get message type badge class.
     */
    public function getMessageTypeBadgeClass()
    {
        return [
            'text' => 'bg-blue-100 text-blue-800',
            'file' => 'bg-purple-100 text-purple-800',
            'system' => 'bg-gray-100 text-gray-800',
            'notification' => 'bg-yellow-100 text-yellow-800'
        ][$this->message_type] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * Get unread messages for a user.
     */
    public static function getUnreadMessages($userEmail)
    {
        return self::where('receiver_email', $userEmail)
            ->where('is_read', false)
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get conversation between two users.
     */
    public static function getConversation($userEmail1, $userEmail2, $limit = 50)
    {
        return self::where(function ($query) use ($userEmail1, $userEmail2) {
            $query->where('sender_email', $userEmail1)
                  ->where('receiver_email', $userEmail2);
        })->orWhere(function ($query) use ($userEmail1, $userEmail2) {
            $query->where('sender_email', $userEmail2)
                  ->where('receiver_email', $userEmail1);
        })->orderBy('created_at', 'desc')
          ->limit($limit)
          ->get()
          ->reverse();
    }

    /**
     * Get property conversation.
     */
    public static function getPropertyConversation($propertyId, $limit = 50)
    {
        return self::where('property_id', $propertyId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse();
    }

    /**
     * Get maintenance request conversation.
     */
    public static function getMaintenanceRequestConversation($maintenanceRequestId, $limit = 50)
    {
        return self::where('maintenance_request_id', $maintenanceRequestId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse();
    }

    /**
     * Get chat statistics.
     */
    public static function getStatistics($userEmail = null)
    {
        $query = self::query();

        if ($userEmail) {
            $query->where(function ($q) use ($userEmail) {
                $q->where('sender_email', $userEmail)
                  ->orWhere('receiver_email', $userEmail);
            });
        }

        $baseQuery = clone $query;

        return [
            'total_messages' => $query->count(),
            'unread_messages' => $baseQuery->where('is_read', false)->count(),
            'by_type' => [
                'text' => $baseQuery->where('message_type', 'text')->count(),
                'file' => $baseQuery->where('message_type', 'file')->count(),
                'system' => $baseQuery->where('message_type', 'system')->count(),
                'notification' => $baseQuery->where('message_type', 'notification')->count()
            ],
            'active_conversations' => $baseQuery
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->distinct('property_id')
                ->count('property_id')
        ];
    }

    /**
     * Get the signed URL for the file attachment.
     */
    public function getFileUrl($expiresIn = 3600)
    {
        if ($this->file_path) {
            return $this->supabase()->getSignedUrl('chat-files', $this->file_path, $expiresIn);
        }
        return null;
    }
}
