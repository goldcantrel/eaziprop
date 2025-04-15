<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained('users');
            $table->decimal('amount', 10, 2);
            $table->string('payment_method'); // credit_card, bank_transfer, cash
            $table->string('status'); // pending, completed, failed
            $table->date('due_date');
            $table->date('payment_date')->nullable();
            $table->string('transaction_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};