<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            AdminUserSeeder::class,
            CourseCategorySeeder::class,
            CourseLevelSeeder::class,
            IndustrySeeder::class,
            AppLanguageSeeder::class,
            SampleDataSeeder::class,
        ]);
    }
}
