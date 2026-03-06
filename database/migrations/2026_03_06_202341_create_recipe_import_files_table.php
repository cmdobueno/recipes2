<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recipe_import_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_import_id')->constrained('recipe_imports')->cascadeOnDelete();
            $table->string('disk');
            $table->string('path', 2048);
            $table->string('original_name');
            $table->string('mime_type', 255);
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index(['recipe_import_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_import_files');
    }
};
