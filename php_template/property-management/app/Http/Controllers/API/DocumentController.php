<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $query = Document::query();

        if ($request->user()->role === 'tenant') {
            $query->whereHas('property.rentals', function ($q) use ($request) {
                $q->where('tenant_id', $request->user()->id);
            });
        } elseif ($request->user()->role === 'landlord') {
            $query->whereHas('property', function ($q) use ($request) {
                $q->where('owner_id', $request->user()->id);
            });
        }

        if ($request->has('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $documents = $query->with(['property', 'uploadedBy'])
                          ->orderBy('created_at', 'desc')
                          ->paginate(10);

        return response()->json($documents);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:lease,insurance,inspection,maintenance,other',
            'file' => 'required|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png',
            'description' => 'nullable|string',
            'expiry_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $property = Property::findOrFail($request->property_id);

        // Check authorization
        if ($request->user()->role === 'tenant') {
            $isAuthorized = $property->rentals()
                ->where('tenant_id', $request->user()->id)
                ->where('status', 'active')
                ->exists();

            if (!$isAuthorized) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        } elseif ($request->user()->role === 'landlord' && $property->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $file = $request->file('file');
        $path = $file->store('documents', 'public');

        $document = Document::create([
            'property_id' => $request->property_id,
            'uploaded_by' => $request->user()->id,
            'title' => $request->title,
            'type' => $request->type,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'description' => $request->description,
            'expiry_date' => $request->expiry_date
        ]);

        return response()->json([
            'message' => 'Document uploaded successfully',
            'document' => $document->load(['property', 'uploadedBy'])
        ], 201);
    }

    public function show(Document $document)
    {
        $user = $request->user();

        if ($user->role === 'tenant') {
            $isAuthorized = $document->property->rentals()
                ->where('tenant_id', $user->id)
                ->where('status', 'active')
                ->exists();

            if (!$isAuthorized) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        } elseif ($user->role === 'landlord' && $document->property->owner_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'document' => $document->load(['property', 'uploadedBy'])
        ]);
    }

    public function update(Request $request, Document $document)
    {
        if (!in_array($request->user()->role, ['landlord', 'admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($request->user()->role === 'landlord' && 
            $document->property->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'type' => 'string|in:lease,insurance,inspection,maintenance,other',
            'description' => 'nullable|string',
            'expiry_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $document->update($request->only([
            'title',
            'type',
            'description',
            'expiry_date'
        ]));

        return response()->json([
            'message' => 'Document updated successfully',
            'document' => $document->load(['property', 'uploadedBy'])
        ]);
    }

    public function destroy(Request $request, Document $document)
    {
        if (!in_array($request->user()->role, ['landlord', 'admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($request->user()->role === 'landlord' && 
            $document->property->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete the file from storage
        Storage::disk('public')->delete($document->file_path);

        $document->delete();

        return response()->json([
            'message' => 'Document deleted successfully'
        ]);
    }

    public function download(Document $document)
    {
        $user = $request->user();

        if ($user->role === 'tenant') {
            $isAuthorized = $document->property->rentals()
                ->where('tenant_id', $user->id)
                ->where('status', 'active')
                ->exists();

            if (!$isAuthorized) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        } elseif ($user->role === 'landlord' && $document->property->owner_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!Storage::disk('public')->exists($document->file_path)) {
            return response()->json([
                'message' => 'File not found'
            ], 404);
        }

        return Storage::disk('public')->download(
            $document->file_path,
            $document->file_name
        );
    }
}