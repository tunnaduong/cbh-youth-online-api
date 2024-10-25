<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForumMainCategory extends Model
{
    protected $table = "cyo_forum_main_categories";

    protected $fillable = ['name', 'description'];

    public function subforums()
    {
        return $this->hasMany(ForumSubforum::class, 'main_category_id');
    }
}
