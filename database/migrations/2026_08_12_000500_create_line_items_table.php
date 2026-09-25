<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('line_items')) {
            return;
        }

        Schema::create('line_items', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 10, 2);

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_items');
    }
};