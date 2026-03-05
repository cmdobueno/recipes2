<?php

use App\Enums\RecipeImportMethod;
use App\Enums\RecipeImportStatus;
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
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->unsignedInteger('servings')->nullable();
            $table->unsignedInteger('prep_minutes')->nullable();
            $table->unsignedInteger('cook_minutes')->nullable();
            $table->unsignedInteger('total_minutes')->nullable();
            $table->unsignedInteger('calories_per_serving')->nullable();
            $table->json('ingredients');
            $table->json('instructions');
            $table->text('notes')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('source_domain')->nullable();
            $table->string('source_title')->nullable();
            $table->enum('import_status', array_column(RecipeImportStatus::cases(), 'value'))->default(RecipeImportStatus::Draft->value);
            $table->enum('import_method', array_column(RecipeImportMethod::cases(), 'value'))->nullable();
            $table->text('import_error')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_published');
            $table->index('import_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
