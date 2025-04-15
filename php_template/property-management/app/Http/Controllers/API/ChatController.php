<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Events\NewMessageEvent;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $query = ChatMessage::query();

        // Get messages where user is either sender or receiver
        $query->where(function ($q) use ($request) {
            $q->where('sender_id', $request->user()->id)
              ->orWhere('receiver_id', $request->user()->id);
        });

        if ($request->has('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->has('maintenance_request_id')) {
            $query->where('maintenance_request_id', $request->maintenance_request_id);
        }

        if ($request->has('user_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('sender_id', $request->user_id)
                  ->orWhere('receiver_id', $request->user_id);
            });
        }

        $messages = $query->with(['sender', 'receiver', 'property'])
                         ->orderBy('created_at', 'desc')
                         ->paginate(20);

        return response()->json($messages);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required_without:file|string',
            'property_id' => 'nullable|exists:properties,id',
            'maintenance_request_id' => 'nullable|exists:maintenance_requests,id',
            'message_type' => 'required|string|in:text,file,image',
            'file' => 'required_if:message_type,file,image|file|max:5120'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if users are related through property/rental
        $receiver = User::findOrFail($request->receiver_id);
        $isRelated = false;

        if ($request->has('property_id')) {
            $isRelated = $this->checkPropertyRelation(
                $request->user(),
                $receiver,
                $request->property_id
            );
        }

        if (!$isRelated && !in_array($request->user()->role, ['admin', 'staff'])) {
            return response()->json([
                'message' => 'You are not authorized to message this user'
            ], 403);
        }

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('chat_files', 'public');
        }

        $message = ChatMessage::create([
            'sender_id' => $request->user()->id,
            'receiver_id' => $request->receiver_id,
            'message' => $request->message,
            'property_id' => $request->property_id,
            'maintenance_request_id' => $request->maintenance_request_id,
            'message_type' => $request->message_type,
            'file_path' => $filePath
        ]);

        // Broadcast the new message
        broadcast(new NewMessageEvent($message))->toOthers();

        return response()->json([
            'message' => 'Message sent successfully',
            'chat_message' => $message->load(['sender', 'receiver'])
        ], 201);
    }

    public function markAsRead(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message_ids' => 'required|array',
            'message_ids.*' => 'exists:chat_messages,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        ChatMessage::whereIn('id', $request->message_ids)
            ->where('receiver_id', $request->user()->id)
            ->update(['is_read' => true]);

        return response()->json([
            'message' => 'Messages marked as read'
        ]);
    }

    public function getUnreadCount(Request $request)
    {
        $count = ChatMessage::where('receiver_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'unread_count' => $count
        ]);
    }

    public function getConversations(Request $request)
    {
        $conversations = ChatMessage::where('sender_id', $request->user()->id)
            ->orWhere('receiver_id', $request->user()->id)
            ->select('sender_id', 'receiver_id')
            ->distinct()
            ->with(['sender', 'receiver'])
            ->get()
            ->map(function ($message) use ($request) {
                $otherUser = $message->sender_id === $request->user()->id
                    ? $message->receiver
                    : $message->sender;

                $lastMessage = ChatMessage::where(function ($query) use ($request, $otherUser) {
                    $query->where(function ($q) use ($request, $otherUser) {
                        $q->where('sender_id', $request->user()->id)
                          ->where('receiver_id', $otherUser->id);
                    })->orWhere(function ($q) use ($request, $otherUser) {
                        $q->where('sender_id', $otherUser->id)
                          ->where('receiver_id', $request->user()->id);
                    });
                })
                ->latest()
                ->first();

                return [
                    'user' => $otherUser,
                    'last_message' => $lastMessage,
                    'unread_count' => ChatMessage::where('sender_id', $otherUser->id)
                        ->where('receiver_id', $request->user()->id)
                        ->where('is_read', false)
                        ->count()
                ];
            });

        return response()->json($conversations);
    }

    private function checkPropertyRelation(User $user1, User $user2, $propertyId)
    {
        if ($user1->role === 'landlord') {
            return $user1->properties()->where('id', $propertyId)
                ->whereHas('rentals', function ($query) use ($user2) {
                    $query->where('tenant_id', $user2->id);
                })
                ->exists();
        }

        if ($user1->role === 'tenant') {
            return $user1->rentals()
                ->where('property_id', $propertyId)
                ->whereHas('property', function ($query) use ($user2) {
                    $query->where('owner_id', $user2->id);
                })
                ->exists();
        }

        return false;
    }
}