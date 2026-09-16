<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'color', 'sort_order'];

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
