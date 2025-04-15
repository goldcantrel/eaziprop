<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Events\NewChatMessage;

class ChatController extends Controller
{
    public function getMessages(Request $request, $property_id)
    {
        $property = Property::findOrFail($property_id);
        $this->authorize('view', $property);

        $messages = ChatMessage::where('property_id', $property_id)
            ->where(function($query) {
                $query->where('sender_id', Auth::id())
                    ->orWhere('recipient_id', Auth::id());
            })
            ->with(['sender', 'recipient'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json($messages);
    }

    public function sendMessage(Request $request, $property_id)
    {
        $property = Property::findOrFail($property_id);
        $this->authorize('chat', $property);

        $request->validate([
            'recipient_id' => 'required|exists:users,id',
            'message' => 'required|string',
            'type' => 'required|in:text,image,file'
        ]);

        $message = ChatMessage::create([
            'property_id' => $property_id,
            'sender_id' => Auth::id(),
            'recipient_id' => $request->recipient_id,
            'message' => $request->message,
            'type' => $request->type
        ]);

        broadcast(new NewChatMessage($message))->toOthers();

        return response()->json($message->load(['sender', 'recipient']), 201);
    }

    public function markAsRead(Request $request, $property_id)
    {
        $request->validate([
            'message_ids' => 'required|array',
            'message_ids.*' => 'exists:chat_messages,id'
        ]);

        ChatMessage::whereIn('id', $request->message_ids)
            ->where('recipient_id', Auth::id())
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Messages marked as read']);
    }

    public function deleteMessage(ChatMessage $message)
    {
        $this->authorize('delete', $message);
        $message->delete();
        return response()->json(null, 204);
    }
}