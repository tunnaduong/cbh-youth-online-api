<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    use HasFactory;

    protected $table = 'cyo_topics';

    // Define which fields are mass assignable
    protected $fillable = [
        'user_id',
        'title',
        'description',
    ];

    // Define the relationship: A topic belongs to a user
    public function user()
    {
        return $this->belongsTo(AuthAccount::class, 'user_id');
    }

    public function views()
    {
        return $this->hasMany(TopicView::class);
    }

    public function votes()
    {
        return $this->hasMany(TopicVote::class);
    }

    public function comments()
    {
        return $this->hasMany(TopicComment::class);
    }
}
