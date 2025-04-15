<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Property;
use App\Services\SupabaseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DocumentController extends Controller
{
    protected $supabase;
    protected $allowedMimes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/png',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];

    public function __construct(SupabaseService $supabase)
    {
        $this->supabase = $supabase;
    }

    /**
     * Display a listing of documents.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $this->supabase->getUser($request->header('Authorization'));
            $query = Document::query();

            // Filter by property owner or tenant
            $query->whereHas('property', function ($q) use ($user) {
                $q->where('owner_email', $user->email)
                  ->orWhereHas('rentals', function ($q2) use ($user) {
                      $q2->where('tenant_email', $user->email)
                        ->where('status', 'active');
                  });
            });

            // Filter by property
            if ($request->has('property_id')) {
                $query->where('property_id', $request->property_id);
            }

            // Filter by type
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Filter by uploader
            if ($request->has('uploaded_by')) {
                $query->where('uploaded_by_email', $request->uploaded_by);
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->where('created_at', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->where('created_at', '<=', $request->end_date);
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDir = $request->get('sort_dir', 'desc');
            $query->orderBy($sortBy, $sortDir);

            // Load relationships
            $query->with('property');

            // Pagination
            $perPage = $request->get('per_page', 10);
            $documents = $query->paginate($perPage);

            // Generate signed URLs for documents
            foreach ($documents as $document) {
                $document->signed_url = $document->getSignedUrl();
            }

            return response()->json($documents);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new document.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => ['required', 'string', 'exists:properties_593nwd,id'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:lease,insurance,inspection,maintenance,other'],
            'file' => ['required', 'string'], // Base64 encoded file
            'description' => ['nullable', 'string'],
            'expiry_date' => ['nullable', 'date', 'after:today']
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $this->supabase->getUser($request->header('Authorization'));
            $property = Property::find($request->property_id);

            // Check authorization
            if ($property->owner_email !== $user->email) {
                return response()->json([
                    'message' => 'Unauthorized to upload documents for this property'
                ], 403);
            }

            // Decode and validate file
            $fileData = base64_decode($request->file);
            $f = finfo_open();
            $mimeType = finfo_buffer($f, $fileData, FILEINFO_MIME_TYPE);
            finfo_close($f);

            if (!in_array($mimeType, $this->allowedMimes)) {
                return response()->json([
                    'message' => 'Invalid file type'
                ], 422);
            }

            // Generate unique filename
            $extension = $this->getExtensionFromMime($mimeType);
            $filename = uniqid() . '.' . $extension;
            $path = "documents/{$property->id}/{$filename}";

            // Upload file to Supabase Storage
            $this->supabase->uploadFile('documents', $path, $request->file);

            // Create document record
            $document = new Document([
                'property_id' => $request->property_id,
                'uploaded_by_email' => $user->email,
                'title' => $request->title,
                'type' => $request->type,
                'file_path' => $path,
                'file_name' => $filename,
                'file_type' => $mimeType,
                'file_size' => strlen($fileData),
                'description' => $request->description,
                'expiry_date' => $request->expiry_date
            ]);
            $document->save();

            // Generate signed URL
            $document->signed_url = $document->getSignedUrl();

            return response()->json([
                'message' => 'Document uploaded successfully',
                'document' => $document
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified document.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $document = Document::with('property')->find($id);

            if (!$document) {
                return response()->json([
                    'message' => 'Document not found'
                ], 404);
            }

            // Generate signed URL
            $document->signed_url = $document->getSignedUrl();

            return response()->json($document);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified document.
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'in:lease,insurance,inspection,maintenance,other'],
            'description' => ['nullable', 'string'],
            'expiry_date' => ['nullable', 'date', 'after:today']
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $this->supabase->getUser($request->header('Authorization'));
            $document = Document::with('property')->find($id);

            if (!$document) {
                return response()->json([
                    'message' => 'Document not found'
                ], 404);
            }

            // Check authorization
            if ($document->property->owner_email !== $user->email) {
                return response()->json([
                    'message' => 'Unauthorized to update this document'
                ], 403);
            }

            $document->update($request->all());

            return response()->json([
                'message' => 'Document updated successfully',
                'document' => $document
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified document.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $user = $this->supabase->getUser($request->header('Authorization'));
            $document = Document::with('property')->find($id);

            if (!$document) {
                return response()->json([
                    'message' => 'Document not found'
                ], 404);
            }

            // Check authorization
            if ($document->property->owner_email !== $user->email) {
                return response()->json([
                    'message' => 'Unauthorized to delete this document'
                ], 403);
            }

            // Delete file from Supabase Storage
            $this->supabase->deleteFile('documents', $document->file_path);

            // Delete document record
            $document->delete();

            return response()->json([
                'message' => 'Document deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get document statistics.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics(Request $request)
    {
        try {
            $user = $this->supabase->getUser($request->header('Authorization'));
            $propertyId = $request->get('property_id');

            $statistics = Document::getStatistics($propertyId);

            return response()->json($statistics);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch document statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get file extension from MIME type.
     *
     * @param string $mimeType
     * @return string
     */
    protected function getExtensionFromMime($mimeType)
    {
        return [
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx'
        ][$mimeType] ?? 'bin';
    }
}
