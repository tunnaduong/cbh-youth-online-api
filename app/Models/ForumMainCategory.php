<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForumMainCategory extends Model
{
    protected $fillable = ['name', 'description'];

    public function subforums()
    {
        return $this->hasMany(ForumSubforum::class, 'main_category_id');
    }
}
