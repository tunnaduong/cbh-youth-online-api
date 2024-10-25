<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserContent extends Model
{
    use HasFactory;
    protected $table = "cyo_cdn_user_content";

    protected $fillable = ['user_id', 'file_name', 'file_path', 'file_type', 'file_size'];
}
