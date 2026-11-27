<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->decimal('price', 15, 2);
            $table->unsignedInteger('quota');
            $table->unsignedInteger('sold')->default(0);
            $table->unsignedInteger('reserved')->default(0);
            $table->unsignedInteger('min_per_order')->default(1);
            $table->unsignedInteger('max_per_order')->default(10);
            $table->dateTime('sale_start')->nullable();
            $table->dateTime('sale_end')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'status']);
        });

        // Business rule 13: sold + reserved may never exceed quota (defence in depth).
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE ticket_types ADD CONSTRAINT chk_ticket_types_capacity CHECK (sold + reserved <= quota)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_types');
    }
};
