<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'rental_id',
        'uploaded_by',
        'title',
        'file_path',
        'file_type',
        'document_type',
        'description',
        'is_private'
    ];

    protected $casts = [
        'is_private' => 'boolean'
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function rental()
    {
        return $this->belongsTo(Rental::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isLease()
    {
        return $this->document_type === 'lease';
    }

    public function isInvoice()
    {
        return $this->document_type === 'invoice';
    }

    public function isContract()
    {
        return $this->document_type === 'contract';
    }
}