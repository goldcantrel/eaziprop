<?php

namespace App\Models;

use Carbon\Carbon;

class Document extends SupabaseModel
{
    protected $table = 'documents';

    protected $fillable = [
        'property_id',
        'uploaded_by_email',
        'title',
        'type',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'description',
        'expiry_date'
    ];

    protected $casts = [
        'file_size' => 'integer',
        'expiry_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the property associated with the document.
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the file size in a human-readable format.
     */
    public function getHumanFileSizeAttribute()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf('%.2f %s', $bytes / pow(1024, $factor), $units[$factor]);
    }

    /**
     * Check if the document is expired.
     */
    public function isExpired()
    {
        return $this->expiry_date && Carbon::now()->greaterThan($this->expiry_date);
    }

    /**
     * Get the document type badge class.
     */
    public function getTypeBadgeClass()
    {
        return [
            'lease' => 'bg-blue-100 text-blue-800',
            'insurance' => 'bg-green-100 text-green-800',
            'inspection' => 'bg-yellow-100 text-yellow-800',
            'maintenance' => 'bg-red-100 text-red-800',
            'other' => 'bg-gray-100 text-gray-800'
        ][$this->type] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * Get the document icon class based on file type.
     */
    public function getFileIconClass()
    {
        $extension = pathinfo($this->file_name, PATHINFO_EXTENSION);
        
        return [
            'pdf' => 'fa-file-pdf text-red-500',
            'doc' => 'fa-file-word text-blue-500',
            'docx' => 'fa-file-word text-blue-500',
            'xls' => 'fa-file-excel text-green-500',
            'xlsx' => 'fa-file-excel text-green-500',
            'jpg' => 'fa-file-image text-purple-500',
            'jpeg' => 'fa-file-image text-purple-500',
            'png' => 'fa-file-image text-purple-500'
        ][strtolower($extension)] ?? 'fa-file text-gray-500';
    }

    /**
     * Get documents that are expiring soon.
     */
    public static function expiringSoon($days = 30)
    {
        return self::whereNotNull('expiry_date')
            ->where('expiry_date', '>=', Carbon::now())
            ->where('expiry_date', '<=', Carbon::now()->addDays($days));
    }

    /**
     * Get expired documents.
     */
    public static function expired()
    {
        return self::whereNotNull('expiry_date')
            ->where('expiry_date', '<', Carbon::now());
    }

    /**
     * Get document statistics.
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
            'by_type' => [
                'lease' => $baseQuery->where('type', 'lease')->count(),
                'insurance' => $baseQuery->where('type', 'insurance')->count(),
                'inspection' => $baseQuery->where('type', 'inspection')->count(),
                'maintenance' => $baseQuery->where('type', 'maintenance')->count(),
                'other' => $baseQuery->where('type', 'other')->count()
            ],
            'storage_used' => $baseQuery->sum('file_size'),
            'expiring_soon' => $baseQuery->expiringSoon()->count(),
            'expired' => $baseQuery->expired()->count()
        ];
    }

    /**
     * Get the signed URL for the document.
     */
    public function getSignedUrl($expiresIn = 3600)
    {
        return $this->supabase()->getSignedUrl('documents', $this->file_path, $expiresIn);
    }
}
