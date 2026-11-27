<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('officer_id')->constrained('users')->restrictOnDelete();
            $table->string('result', 30);
            $table->dateTime('scanned_at');
            $table->string('device_info')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_scans');
    }
};
