<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An embeddable HTML5 game shown in the Game section.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $category
 * @property string $image_url
 * @property string $iframe_url
 * @property string $platform pc|mobile|both
 * @property bool $is_active
 * @property int $sort_order
 */
class Game extends Model
{
  protected $table = 'cyo_games';

  protected $fillable = [
    'name',
    'slug',
    'description',
    'category',
    'image_url',
    'iframe_url',
    'platform',
    'is_active',
    'sort_order',
  ];

  protected $casts = [
    'is_active' => 'boolean',
  ];

  public function sessions(): HasMany
  {
    return $this->hasMany(GameSession::class);
  }
}
