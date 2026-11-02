<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['external_id', 'sync_token', 'search_text', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['images' => 'array', 'attributes' => 'array', 'price' => 'float', 'stock' => 'integer', 'active' => 'boolean'];
    }
}
