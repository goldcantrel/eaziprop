<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_id')->constrained('users');
            $table->string('address');
            $table->string('type'); // apartment, house, condo, etc.
            $table->integer('bedrooms');
            $table->integer('bathrooms');
            $table->decimal('square_feet', 10, 2);
            $table->decimal('rent_amount', 10, 2);
            $table->text('description')->nullable();
            $table->json('amenities')->nullable();
            $table->boolean('is_available')->default(true);
            $table->string('status')->default('active'); // active, maintenance, inactive
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};