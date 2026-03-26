<?php

namespace Database\Seeders;

use App\Models\CourseLevel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CourseLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            'Beginner',
            'Intermediate',
            'Advanced',
            'All Levels',
        ];

        foreach ($levels as $name) {
            CourseLevel::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true]
            );
        }
    }
}
