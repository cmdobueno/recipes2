<?php

use App\Enums\RecipeImportAttemptStatus;
use App\Enums\RecipeImportMethod;
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
        Schema::create('recipe_imports', function (Blueprint $table) {
            $table->id();
            $table->string('source_url', 2048);
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipe_id')->nullable()->constrained('recipes')->nullOnDelete();
            $table->enum('status', array_column(RecipeImportAttemptStatus::cases(), 'value'));
            $table->enum('method_used', array_column(RecipeImportMethod::cases(), 'value'))->nullable();
            $table->json('raw_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_imports');
    }
};
