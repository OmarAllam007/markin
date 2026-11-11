<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['contacted_at' => 'datetime'];
    }
}
