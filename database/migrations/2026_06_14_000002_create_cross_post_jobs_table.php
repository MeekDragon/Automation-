<?php
// database/migrations/2026_06_14_000002_create_cross_post_jobs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cross_post_jobs', function (Blueprint $table) {
            $table->string('id')->primary(); // string ID e.g. job_1781385293359
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('media_path');
            $table->string('status')->default('PENDING'); // PENDING, PROCESSING, COMPLETED, FAILED, SCHEDULED
            $table->json('platform_options')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('created_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cross_post_jobs');
    }
};
