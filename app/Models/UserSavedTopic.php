<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSavedTopic extends Model
{
    // Specify the correct table name
    protected $table = 'cyo_user_saved_topics';

    protected $fillable = [
        'user_id',
        'topic_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }
}
