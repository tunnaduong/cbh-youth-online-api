<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopicView extends Model
{
    use HasFactory;

    protected $table = 'cyo_topic_views';

    protected $fillable = [
        'topic_id',
        'user_id',
    ];

    // Define the relationship with Topic
    public function topic()
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }

    // Define the relationship with AuthAccount
    public function user()
    {
        return $this->belongsTo(AuthAccount::class, 'user_id');
    }
}
