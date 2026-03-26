<?php

namespace Database\Seeders;

use App\Models\AppLanguage;
use Illuminate\Database\Seeder;

class AppLanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['name' => 'English',            'code' => 'en'],
            ['name' => 'Arabic',             'code' => 'ar'],
            ['name' => 'French',             'code' => 'fr'],
            ['name' => 'German',             'code' => 'de'],
            ['name' => 'Spanish',            'code' => 'es'],
            ['name' => 'Chinese (Mandarin)', 'code' => 'zh'],
            ['name' => 'Hindi',              'code' => 'hi'],
            ['name' => 'Urdu',               'code' => 'ur'],
            ['name' => 'Portuguese',         'code' => 'pt'],
            ['name' => 'Russian',            'code' => 'ru'],
            ['name' => 'Japanese',           'code' => 'ja'],
            ['name' => 'Korean',             'code' => 'ko'],
            ['name' => 'Italian',            'code' => 'it'],
            ['name' => 'Turkish',            'code' => 'tr'],
            ['name' => 'Dutch',              'code' => 'nl'],
            ['name' => 'Swedish',            'code' => 'sv'],
            ['name' => 'Polish',             'code' => 'pl'],
            ['name' => 'Greek',              'code' => 'el'],
            ['name' => 'Hebrew',             'code' => 'he'],
            ['name' => 'Indonesian',         'code' => 'id'],
            ['name' => 'Malay',              'code' => 'ms'],
            ['name' => 'Bengali',            'code' => 'bn'],
            ['name' => 'Persian (Farsi)',    'code' => 'fa'],
            ['name' => 'Thai',               'code' => 'th'],
            ['name' => 'Vietnamese',         'code' => 'vi'],
        ];

        foreach ($languages as $lang) {
            AppLanguage::firstOrCreate(
                ['code' => $lang['code']],
                ['name' => $lang['name'], 'is_active' => true]
            );
        }
    }
}
