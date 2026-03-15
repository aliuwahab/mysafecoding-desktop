<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ignored_findings', function (Blueprint $table) {
            $table->id();
            $table->string('project_path');
            $table->string('scanner');       // trivy, semgrep, gitleaks, laravel_rules, env_audit
            $table->string('fingerprint');   // unique identifier for the finding
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['project_path', 'scanner', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ignored_findings');
    }
};
