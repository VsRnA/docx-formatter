<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('type', 32);
            $table->unsignedInteger('sort')->default(0);
            $table->longText('html')->nullable();
            $table->text('text_original')->nullable();
            $table->text('text_translated')->nullable();
            $table->string('translation_status', 32)->default('pending');
            $table->json('styles_json')->nullable();
            $table->json('meta_json')->nullable();
            $table->json('assets_json')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_blocks');
    }
};
