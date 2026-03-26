<?php

namespace Database\Seeders;

use App\Models\Industry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class IndustrySeeder extends Seeder
{
    public function run(): void
    {
        $industries = [
            'Technology & Software',
            'Education & Training',
            'Finance & Accounting',
            'Healthcare & Medicine',
            'Marketing & Advertising',
            'Design & Creative Arts',
            'Business & Management',
            'Engineering',
            'Data Science & AI',
            'Cybersecurity',
            'Sales & Business Development',
            'Human Resources',
            'Legal & Compliance',
            'Real Estate',
            'Media & Entertainment',
            'Consulting',
            'E-commerce & Retail',
            'Research & Development',
            'Non-Profit & Social Work',
            'Construction & Architecture',
            'Supply Chain & Logistics',
            'Automotive',
            'Agriculture & Environment',
            'Sports & Fitness',
            'Travel & Hospitality',
        ];

        foreach ($industries as $name) {
            Industry::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true]
            );
        }
    }
}
