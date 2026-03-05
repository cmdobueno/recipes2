<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
        ]);

        User::query()->updateOrCreate(
            ['email' => 'admin@familyrecipes.test'],
            [
                'name' => 'Family Admin',
                'password' => 'password',
                'is_admin' => true,
            ],
        );
    }
}
