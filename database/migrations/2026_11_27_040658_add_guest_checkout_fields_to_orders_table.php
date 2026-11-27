<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('buyer_name', 100)->nullable()->after('user_id');
            $table->string('buyer_email', 150)->nullable()->after('buyer_name')->index();
            $table->string('buyer_phone', 30)->nullable()->after('buyer_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['buyer_email']);
            $table->dropColumn(['buyer_name', 'buyer_email', 'buyer_phone']);
        });
    }
};
