<?php
// database/migrations/2026_06_14_000003_create_job_destinations_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_destinations', function (Blueprint $table) {
            $table->id();
            $table->string('job_id');
            $table->string('platform_key'); // e.g. 'youtube_video', 'instagram_reel', etc.
            $table->string('status')->default('PENDING'); // PENDING, PROCESSING, COMPLETED, FAILED
            $table->text('error')->nullable();
            $table->text('external_id')->nullable(); // stores permalinks
            $table->timestamps();

            $table->foreign('job_id')->references('id')->on('cross_post_jobs')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_destinations');
    }
};
