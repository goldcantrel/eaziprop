<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('rental_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('title');
            $table->string('file_path');
            $table->string('file_type');
            $table->string('document_type'); // lease, invoice, receipt, contract, other
            $table->text('description')->nullable();
            $table->boolean('is_private')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};