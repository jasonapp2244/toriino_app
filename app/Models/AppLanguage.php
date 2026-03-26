<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppLanguage extends Model
{
    protected $table = 'app_languages';

    protected $fillable = ['name', 'code', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
