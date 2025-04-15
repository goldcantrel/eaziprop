<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'message',
        'is_read',
        'property_id',
        'maintenance_request_id',
        'message_type',
        'file_path'
    ];

    protected $casts = [
        'is_read' => 'boolean'
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function maintenanceRequest()
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    public function isFile()
    {
        return $this->message_type === 'file';
    }

    public function isImage()
    {
        return $this->message_type === 'image';
    }
}