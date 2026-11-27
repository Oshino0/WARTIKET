<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // One order can have several payment attempts; each gets its own reference.
            $table->string('reference_id')->nullable()->unique()->after('order_id');
            $table->string('session_id')->nullable()->index()->after('transaction_id');
            $table->text('payment_url')->nullable()->after('session_id');
            $table->dateTime('expires_at')->nullable()->after('paid_at');
            // Set when money arrived but the order could not be fulfilled (late, duplicate...).
            $table->boolean('needs_review')->default(false)->index();
            $table->string('review_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['reference_id', 'session_id', 'payment_url', 'expires_at', 'needs_review', 'review_note']);
        });
    }
};
