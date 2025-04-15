<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        if ($user->role === 'superuser') {
            $documents = Document::with(['property', 'user'])->get();
        } elseif ($user->role === 'landlord') {
            $documents = Document::whereHas('property', function($query) use ($user) {
                $query->where('landlord_id', $user->id);
            })->with(['property', 'user'])->get();
        } else {
            $documents = Document::where('user_id', $user->id)
                ->orWhereHas('property.rentals', function($query) use ($user) {
                    $query->where('tenant_id', $user->id);
                })->with(['property'])->get();
        }

        return response()->json($documents);
    }

    public function store(Request $request)
    {
        $request->validate([
            'property_id' => 'required|exists:properties,id',
            'title' => 'required|string|max:255',
            'type' => 'required|in:lease,contract,invoice,receipt,maintenance,other',
            'file' => 'required|file|max:10240', // 10MB max
            'description' => 'nullable|string'
        ]);

        $file = $request->file('file');
        $path = $file->store('documents', 's3');

        $document = Document::create([
            'property_id' => $request->property_id,
            'user_id' => Auth::id(),
            'title' => $request->title,
            'type' => $request->type,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'description' => $request->description,
            'status' => 'pending'
        ]);

        return response()->json($document, 201);
    }

    public function show(Document $document)
    {
        $this->authorize('view', $document);
        return response()->json($document->load(['property', 'user']));
    }

    public function update(Request $request, Document $document)
    {
        $this->authorize('update', $document);

        $request->validate([
            'title' => 'string|max:255',
            'type' => 'in:lease,contract,invoice,receipt,maintenance,other',
            'status' => 'in:pending,approved,rejected',
            'description' => 'nullable|string'
        ]);

        $document->update($request->validated());
        return response()->json($document);
    }

    public function destroy(Document $document)
    {
        $this->authorize('delete', $document);
        
        // Delete file from S3
        Storage::disk('s3')->delete($document->file_path);
        
        $document->delete();
        return response()->json(null, 204);
    }

    public function download(Document $document)
    {
        $this->authorize('view', $document);
        
        $url = Storage::disk('s3')->temporaryUrl(
            $document->file_path,
            now()->addMinutes(5)
        );

        return response()->json(['download_url' => $url]);
    }
}