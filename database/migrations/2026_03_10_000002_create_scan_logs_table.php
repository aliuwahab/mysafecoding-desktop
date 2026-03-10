<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->text('line');
            $table->string('scanner')->default('system'); // trivy, semgrep, gitleaks, system
            $table->string('level')->default('info'); // info, error, warning
            $table->timestamps();

            $table->index(['scan_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_logs');
    }
};
