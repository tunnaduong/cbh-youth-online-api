<?php

namespace App\Services;

use App\Models\AuthAccount;
use Illuminate\Validation\Rule;

/**
 * Profile appearance customization, modelled on Discord's profile editor
 * (its free + Nitro options, all unlocked with activity points instead).
 *
 * Stored as JSON in cyo_user_profiles.profile_theme:
 *   {
 *     "primary_color": "#rrggbb" | null,   Profile Theme (null = normal look)
 *     "accent_color": "#rrggbb" | null,
 *     "banner_color": "#rrggbb" | null,    banner when there is no cover photo
 *     "name_font": one of OPTIONS['name_font'],
 *     "name_effect": one of OPTIONS['name_effect'],
 *     "name_colors": ["#rrggbb", "#rrggbb"],  gradient uses both, others the first
 *     "avatar_frame": one of OPTIONS['avatar_frame'],
 *     "profile_effect": one of OPTIONS['profile_effect'],
 *     "profile_frame": one of OPTIONS['profile_frame']
 *   }
 *
 * Customizing at all needs the `custom_profile` tier privilege (Thành viên
 * tập sự, 50 points). On top of that each option names the member tier it
 * needs - the fancier the option, the higher the tier. Tiers follow the
 * user's *current* points, so a user can drop below one after saving: the
 * saved JSON is kept as-is and forDisplay() just falls back to the default
 * for whatever is no longer unlocked, so it comes back if they re-qualify.
 *
 * The web app draws every key (src/lib/profileTheme.js, src/lib/nameFonts.js)
 * - keep both sides in sync.
 */
class ProfileThemeService
{
  /** Privilege needed to customize the profile at all. */
  public const PRIVILEGE = 'custom_profile';

  public const DEFAULT_PRIMARY = '#319527';
  public const DEFAULT_ACCENT = '#22d3ee';

  /**
   * Option => tier id it needs (null = only the base privilege). The first
   * key of each list is the default.
   */
  public const OPTIONS = [
    'name_font' => [
      'default' => null,
      'condensed' => null,
      'modern' => null,
      'bubbly' => null,
      'handwritten' => null,
      'script' => 'active',
      'comic' => 'active',
      'pixel' => 'active',
      'tech' => 'active',
      'gothic' => 'distinguished',
      'heavy' => 'distinguished',
      'spooky' => 'distinguished',
    ],
    'name_effect' => [
      'none' => null,
      'solid' => null,
      'gradient' => 'active',
      'pop' => 'active',
      'toon' => 'distinguished',
      'neon' => 'distinguished',
    ],
    'avatar_frame' => [
      'none' => null,
      'theme' => null,
      'trainee' => null,
      'active' => 'active',
      'distinguished' => 'distinguished',
      'veteran' => 'veteran',
    ],
    'profile_effect' => [
      'none' => null,
      'sparkles' => 'active',
      'hearts' => 'active',
      'snow' => 'distinguished',
      'aurora' => 'veteran',
    ],
    'profile_frame' => [
      'none' => null,
      'glow' => 'active',
      'gold' => 'distinguished',
      'neon' => 'veteran',
    ],
  ];

  /** Tier needed to keep an uploaded GIF avatar animated. */
  public const ANIMATED_AVATAR_TIER = 'veteran';

  private const COLOR_FIELDS = ['primary_color', 'accent_color', 'banner_color'];

  private const HEX_COLOR = '/\A#[0-9a-fA-F]{6}\z/';

  /**
   * Validation rules for the `profile_theme` field of a profile update.
   */
  public static function rules(): array
  {
    $keys = implode(',', [...self::COLOR_FIELDS, ...array_keys(self::OPTIONS), 'name_colors']);

    $rules = [
      'profile_theme' => 'nullable|array:' . $keys,
      'profile_theme.name_colors' => ['nullable', 'array', 'min:1', 'max:2'],
      'profile_theme.name_colors.*' => ['string', 'regex:' . self::HEX_COLOR],
    ];

    foreach (self::COLOR_FIELDS as $field) {
      $rules['profile_theme.' . $field] = ['nullable', 'string', 'regex:' . self::HEX_COLOR];
    }

    foreach (self::OPTIONS as $field => $options) {
      $rules['profile_theme.' . $field] = ['nullable', 'string', Rule::in(array_keys($options))];
    }

    return $rules;
  }

  /**
   * Fill in defaults and drop anything outside the known shape.
   */
  public static function normalize(array $theme): array
  {
    $color = fn($value, $default) => is_string($value) && preg_match(self::HEX_COLOR, $value)
      ? strtolower($value)
      : $default;

    $normalized = [];

    foreach (self::COLOR_FIELDS as $field) {
      $normalized[$field] = $color($theme[$field] ?? null, null);
    }

    foreach (self::OPTIONS as $field => $options) {
      $value = $theme[$field] ?? null;
      $normalized[$field] = is_string($value) && array_key_exists($value, $options)
        ? $value
        : array_key_first($options);
    }

    $nameColors = is_array($theme['name_colors'] ?? null) ? array_values($theme['name_colors']) : [];
    $normalized['name_colors'] = [
      $color($nameColors[0] ?? null, self::DEFAULT_PRIMARY),
      $color($nameColors[1] ?? null, self::DEFAULT_ACCENT),
    ];

    return $normalized;
  }

  public static function canCustomize(AuthAccount $user): bool
  {
    return $user->hasPrivilege(self::PRIVILEGE);
  }

  public static function canUseAnimatedAvatar(AuthAccount $user): bool
  {
    return self::canCustomize($user) && self::tierReached($user, self::ANIMATED_AVATAR_TIER);
  }

  /**
   * Validation messages for options in a normalized $theme that the user
   * hasn't unlocked, keyed by field (empty array = everything is allowed).
   */
  public static function lockedErrors(AuthAccount $user, array $theme): array
  {
    $errors = [];

    foreach (self::OPTIONS as $field => $options) {
      $tierId = $options[$theme[$field]];
      if (!self::tierReached($user, $tierId)) {
        $errors['profile_theme.' . $field] = [
          'Tùy chọn này cần đạt ' . self::tierMinPoints($tierId) . ' điểm.',
        ];
      }
    }

    return $errors;
  }

  /**
   * The theme other people should see for this user, or null for the
   * default look (nothing saved, or the base privilege was lost).
   */
  public static function forDisplay(AuthAccount $user): ?array
  {
    $saved = $user->profile->profile_theme ?? null;
    if (empty($saved) || !is_array($saved) || !self::canCustomize($user)) {
      return null;
    }

    $theme = self::normalize($saved);

    foreach (self::OPTIONS as $field => $options) {
      if (!self::tierReached($user, $options[$theme[$field]])) {
        $theme[$field] = array_key_first($options);
      }
    }

    return $theme;
  }

  /**
   * What the owner's editor needs: what they saved, their points and tier
   * progress, and how many points each option needs.
   */
  public static function editorState(AuthAccount $user): array
  {
    $baseTier = self::baseTier();

    $options = [];
    foreach (self::OPTIONS as $field => $map) {
      $options[$field] = collect($map)->map(fn($tierId, $key) => [
        'key' => $key,
        'required_points' => self::tierMinPoints($tierId ?? $baseTier['id']),
        'unlocked' => self::canCustomize($user) && self::tierReached($user, $tierId),
      ])->values()->all();
    }

    $saved = $user->profile->profile_theme ?? null;

    return [
      'can_customize' => self::canCustomize($user),
      'required_points' => $baseTier['min_points'] ?? null,
      'current_points' => $user->getPoints(),
      'tiers' => collect(AuthAccount::tiers())->map(fn($tier) => [
        'id' => $tier['id'],
        'name' => $tier['name'],
        'min_points' => $tier['min_points'],
        'reached' => $user->getPoints() >= $tier['min_points'],
      ])->all(),
      'animated_avatar' => [
        'required_points' => self::tierMinPoints(self::ANIMATED_AVATAR_TIER),
        'unlocked' => self::canUseAnimatedAvatar($user),
      ],
      'saved' => is_array($saved) && !empty($saved) ? self::normalize($saved) : null,
      'options' => $options,
    ];
  }

  private static function tierReached(AuthAccount $user, ?string $tierId): bool
  {
    if ($tierId === null) {
      return true;
    }

    $minPoints = self::tierMinPoints($tierId);

    return $minPoints !== null && $user->getPoints() >= $minPoints;
  }

  private static function tierMinPoints(?string $tierId): ?int
  {
    foreach (AuthAccount::tiers() as $tier) {
      if ($tier['id'] === $tierId) {
        return $tier['min_points'];
      }
    }

    return null;
  }

  /**
   * The lowest tier that grants the base privilege.
   */
  private static function baseTier(): ?array
  {
    foreach (AuthAccount::tiers() as $tier) {
      if (in_array(self::PRIVILEGE, $tier['privileges'])) {
        return $tier;
      }
    }

    return null;
  }
}
