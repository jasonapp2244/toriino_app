<?php

namespace Database\Seeders;

use App\Models\CourseCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CourseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Programming & Development',
            'Web Development',
            'Mobile Development',
            'Data Science & AI',
            'Machine Learning',
            'Cybersecurity',
            'Cloud Computing',
            'DevOps',
            'Database Management',
            'UI/UX Design',
            'Graphic Design',
            'Digital Marketing',
            'Business & Entrepreneurship',
            'Finance & Accounting',
            'Language Learning',
            'Mathematics',
            'Science',
            'Health & Wellness',
            'Music',
            'Photography & Video',
        ];

        foreach ($categories as $name) {
            CourseCategory::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true]
            );
        }
    }
}
