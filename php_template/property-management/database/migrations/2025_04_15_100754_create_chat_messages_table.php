<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users');
            $table->foreignId('receiver_id')->constrained('users');
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->foreignId('property_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('maintenance_request_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('message_type')->default('text'); // text, image, file
            $table->string('file_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};