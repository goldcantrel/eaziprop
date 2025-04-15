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
            $table->foreignId('landlord_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->enum('type', ['apartment', 'house', 'condo', 'commercial']);
            $table->string('address');
            $table->string('city');
            $table->string('state');
            $table->string('zip_code');
            $table->string('country');
            $table->text('description');
            $table->decimal('monthly_rent', 10, 2);
            $table->enum('status', ['available', 'rented', 'maintenance', 'inactive'])->default('available');
            $table->integer('bedrooms');
            $table->integer('bathrooms');
            $table->integer('square_feet');
            $table->date('available_from');
            $table->integer('minimum_lease_period');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};