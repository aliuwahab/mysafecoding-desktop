<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily totals of completed web scans. Only a date and a number: nothing
     * about who scanned or what was scanned.
     */
    public function up(): void
    {
        Schema::create('scan_counts', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->unsignedInteger('scans')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_counts');
    }
};
