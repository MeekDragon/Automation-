<?php
// database/migrations/2026_06_14_000004_create_job_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_logs', function (Blueprint $table) {
            $table->id();
            $table->string('job_id');
            $table->string('platform_key'); // 'system', 'youtube_video', etc.
            $table->text('message');
            $table->string('type')->default('info'); // 'info', 'success', 'error'
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('job_id')->references('id')->on('cross_post_jobs')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_logs');
    }
};
