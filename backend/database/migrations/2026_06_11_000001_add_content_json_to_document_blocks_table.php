<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_blocks', function (Blueprint $table) {
            $table->json('content_json')->nullable()->after('html');
        });
    }

    public function down(): void
    {
        Schema::table('document_blocks', function (Blueprint $table) {
            $table->dropColumn('content_json');
        });
    }
};
