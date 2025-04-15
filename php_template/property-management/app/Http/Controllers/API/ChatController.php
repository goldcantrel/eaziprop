<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Property;
use App\Models\MaintenanceRequest;
use App\Services\SupabaseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    protected $supabase;

    public function __construct(SupabaseService $supabase)
    {
        $this->supabase = $supabase;
    }

    /**
     * Get user's conversations.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function conversations(Request $request)
    {
        try {
            $user = $this->supabase->getUser($request->header('Authorization'));
            $query = ChatMessage::query();

            // Get messages where user is sender or receiver
            $query->where('sender_email', $user->email)
                  ->orWhere('receiver_email', $user->email);

            // Group by conversation
            $conversations = $query->select([
                'property_id',
                'maintenance_request_id',
                'sender_email',
                'receiver_email'
            ])->groupBy([
                'property_id',
                'maintenance_request_id',
                'sender_email',
                'receiver_email'
            ])->with(['property', 'maintenanceRequest'])
              ->orderBy('created_at', 'desc')
              ->get()
              ->map(function ($conversation) use ($user) {
                  // Get other participant's email
                  $otherEmail = $conversation->sender_email === $user->email ?
                      $conversation->receiver_email : $conversation->sender_email;

                  // Get last message
                  $lastMessage = ChatMessage::where(function ($query) use ($conversation) {
                      $query->where('property_id', $conversation->property_id)
                            ->where('maintenance_request_id', $conversation->maintenance_request_id);
                  })->latest()->first();

                  // Get unread count
                  $unreadCount = ChatMessage::where(function ($query) use ($conversation, $user) {
                      $query->where('property_id', $conversation->property_id)
                            ->where('maintenance_request_id', $conversation->maintenance_request_id)
                            ->where('receiver_email', $user->email)
                            ->where('is_read', false);
                  })->count();

                  return [
                      'property' => $conversation->property,
                      'maintenance_request' => $conversation->maintenanceRequest,
                      'other_participant' => $otherEmail,
                      'last_message' => $lastMessage,
                      'unread_count' => $unreadCount
                  ];
              });

            return response()->json($conversations);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch conversations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get messages for a specific conversation.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function messages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => ['required_without:maintenance_request_id', 'string'],
            'maintenance_request_id' => ['required_without:property_id', 'string'],
            'other_participant' => ['required', 'email']
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $this->supabase->getUser($request->header('Authorization'));
            $query = ChatMessage::query();

            // Filter by property or maintenance request
            if ($request->has('property_id')) {
                $query->where('property_id', $request->property_id);
            }
            if ($request->has('maintenance_request_id')) {
                $query->where('maintenance_request_id', $request->maintenance_request_id);
            }

            // Filter conversation participants
            $query->where(function ($q) use ($user, $request) {
                $q->where(function ($q2) use ($user, $request) {
                    $q2->where('sender_email', $user->email)
                       ->where('receiver_email', $request->other_participant);
                })->orWhere(function ($q2) use ($user, $request) {
                    $q2->where('sender_email', $request->other_participant)
                       ->where('receiver_email', $user->email);
                });
            });

            // Apply sorting and pagination
            $perPage = $request->get('per_page', 50);
            $messages = $query->orderBy('created_at', 'desc')
                             ->paginate($perPage);

            // Mark messages as read
            ChatMessage::where('receiver_email', $user->email)
                      ->where('is_read', false)
                      ->update(['is_read' => true]);

            // Generate signed URLs for file attachments
            foreach ($messages as $message) {
                if ($message->file_path) {
                    $message->file_url = $message->getFileUrl();
                }
            }

            return response()->json($messages);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch messages',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send a new message.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => ['required_without:maintenance_request_id', 'string'],
            'maintenance_request_id' => ['required_without:property_id', 'string'],
            'receiver_email' => ['required', 'email'],
            'message' => ['required', 'string'],
            'message_type' => ['required', 'string', 'in:text,file'],
            'file' => ['required_if:message_type,file', 'string'] // Base64 encoded file
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $this->supabase->getUser($request->header('Authorization'));

            // Check authorization
            if ($request->has('property_id')) {
                $property = Property::find($request->property_id);
                if (!$this->canMessageProperty($user->email, $property)) {
                    return response()->json([
                        'message' => 'Unauthorized to send messages for this property'
                    ], 403);
                }
            }

            if ($request->has('maintenance_request_id')) {
                $maintenanceRequest = MaintenanceRequest::find($request->maintenance_request_id);
                if (!$this->canMessageMaintenanceRequest($user->email, $maintenanceRequest)) {
                    return response()->json([
                        'message' => 'Unauthorized to send messages for this maintenance request'
                    ], 403);
                }
            }

            // Handle file upload if present
            $filePath = null;
            if ($request->message_type === 'file') {
                $filename = uniqid() . '.bin';
                $path = "chat-files/{$request->property_id}/{$filename}";
                $this->supabase->uploadFile('chat-files', $path, $request->file);
                $filePath = $path;
            }

            // Create message
            $message = new ChatMessage([
                'property_id' => $request->property_id,
                'maintenance_request_id' => $request->maintenance_request_id,
                'sender_email' => $user->email,
                'receiver_email' => $request->receiver_email,
                'message' => $request->message,
                'message_type' => $request->message_type,
                'file_path' => $filePath
            ]);
            $message->save();

            // Generate file URL if needed
            if ($filePath) {
                $message->file_url = $message->getFileUrl();
            }

            return response()->json([
                'message' => 'Message sent successfully',
                'chat_message' => $message
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get chat statistics.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics(Request $request)
    {
        try {
            $user = $this->supabase->getUser($request->header('Authorization'));
            $statistics = ChatMessage::getStatistics($user->email);

            return response()->json($statistics);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch chat statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if user can message about a property.
     *
     * @param string $userEmail
     * @param Property $property
     * @return bool
     */
    protected function canMessageProperty($userEmail, Property $property)
    {
        // Property owner can always message
        if ($property->owner_email === $userEmail) {
            return true;
        }

        // Check if user is an active tenant
        return $property->rentals()
            ->where('tenant_email', $userEmail)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Check if user can message about a maintenance request.
     *
     * @param string $userEmail
     * @param MaintenanceRequest $request
     * @return bool
     */
    protected function canMessageMaintenanceRequest($userEmail, MaintenanceRequest $request)
    {
        return $request->tenant_email === $userEmail ||
               $request->assigned_to_email === $userEmail ||
               $request->property->owner_email === $userEmail;
    }
}
