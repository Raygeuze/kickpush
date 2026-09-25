<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('expenses');
    }

    public function down(): void
    {
        // The unused legacy table is intentionally not restored.
    }
};