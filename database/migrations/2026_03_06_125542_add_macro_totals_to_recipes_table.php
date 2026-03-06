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
        Schema::table('recipes', function (Blueprint $table) {
            $table->unsignedInteger('total_calories')->nullable()->after('calories_per_serving');
            $table->decimal('total_protein_grams', 8, 1)->nullable()->after('total_calories');
            $table->decimal('total_carbs_grams', 8, 1)->nullable()->after('total_protein_grams');
            $table->decimal('total_fat_grams', 8, 1)->nullable()->after('total_carbs_grams');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn([
                'total_calories',
                'total_protein_grams',
                'total_carbs_grams',
                'total_fat_grams',
            ]);
        });
    }
};
