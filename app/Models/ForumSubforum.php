<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForumSubforum extends Model
{
    protected $fillable = ['main_category_id', 'name', 'description', 'active', 'pinned'];

    public function mainCategory()
    {
        return $this->belongsTo(ForumMainCategory::class, 'main_category_id');
    }
}
