<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $categories = [
            'Breakfast',
            'Lunch',
            'Dinner',
            'Snack',
            'Dessert',
        ];

        foreach ($categories as $categoryName) {
            Category::query()->updateOrCreate(
                ['slug' => str($categoryName)->slug()->value()],
                ['name' => $categoryName],
            );
        }
    }
}
