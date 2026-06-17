<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->unique()->nullable();
            $table->string('source_file_key')->nullable();
            $table->string('language_from', 8)->default('en');
            $table->string('language_to', 8)->default('ru');
            $table->string('status', 32)->default('uploading');
            $table->string('processing_stage', 64)->nullable();
            $table->text('processing_error')->nullable();
            $table->longText('html_draft')->nullable();
            $table->longText('html_published')->nullable();
            $table->string('pdf_key')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
