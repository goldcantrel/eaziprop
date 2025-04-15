<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'sender_id',
        'recipient_id',
        'message',
        'read_at',
        'type'
    ];

    protected $casts = [
        'read_at' => 'datetime'
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}