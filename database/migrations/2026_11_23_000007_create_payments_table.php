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
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('transaction_id')->nullable()->unique();
            $table->string('provider', 30)->default('ipaymu');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->dateTime('paid_at')->nullable();
            $table->json('callback_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
