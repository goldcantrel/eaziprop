<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained('users');
            $table->date('lease_start_date');
            $table->date('lease_end_date');
            $table->decimal('monthly_rent', 10, 2);
            $table->decimal('security_deposit', 10, 2);
            $table->string('lease_terms')->nullable();
            $table->string('status')->default('active'); // active, terminated, expired
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};