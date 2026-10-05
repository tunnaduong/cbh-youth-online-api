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
 *     "primary_color_2" / "accent_color_2" / "banner_color_2": "#rrggbb" | null,
 *                                          second colour = draw that colour as a gradient
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
      // Server-hosted fonts (SERVER_FONTS below) - every key there needs an
      // entry here.
      'flex' => 'premium',
      'grotesk' => 'premium',
      'montserrat' => 'premium',
      'bevietnam' => 'premium',
      'nunito' => 'premium',
      'quicksand' => 'premium',
      'comfortaa' => 'premium',
      'manrope' => 'premium',
      'raleway' => 'premium',
      'exo' => 'premium',
      'playfair' => 'premium',
      'merriweather' => 'premium',
      'robotoslab' => 'premium',
      'lobster' => 'premium',
      'pacifico' => 'premium',
    ],
    'name_effect' => [
      'none' => null,
      'solid' => null,
      'gradient' => 'active',
      'pop' => 'active',
      'toon' => 'distinguished',
      'neon' => 'distinguished',
      // rainbow ignores name_colors; outline uses name_colors[0] as the text
      // colour and name_colors[1] as the border colour.
      'rainbow' => 'premium',
      'outline' => 'premium',
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
    // A small icon shown right after the name (Pro tier). Deliberately
    // playful presets and never a tick: the verified badge must stay
    // unmistakable. The glyph of each key is in NAME_ICONS.
    'name_icon' => [
      'none' => null,
      // The icons of the member tiers themselves (star, bolt, trophy, shield,
      // diamond, crown): by default a member shows the icon of the tier they
      // are in; a Pro member may pick any of them instead, or a preset below.
      'tier_trainee' => 'pro',
      'tier_active' => 'pro',
      'tier_distinguished' => 'pro',
      'tier_veteran' => 'pro',
      'tier_premium' => 'pro',
      'tier_pro' => 'pro',
      'fish' => 'pro',
      'cat' => 'pro',
      'dog' => 'pro',
      'frog' => 'pro',
      'panda' => 'pro',
      'fox' => 'pro',
      'penguin' => 'pro',
      'unicorn' => 'pro',
      'dragon' => 'pro',
      'octopus' => 'pro',
      'butterfly' => 'pro',
      'ghost' => 'pro',
      'alien' => 'pro',
      'robot' => 'pro',
      'rocket' => 'pro',
      'fire' => 'pro',
      'lightning' => 'pro',
      'star' => 'pro',
      'moon' => 'pro',
      'rainbow' => 'pro',
      'crown' => 'pro',
      'gem' => 'pro',
      'clover' => 'pro',
      'cactus' => 'pro',
      'sakura' => 'pro',
      'pizza' => 'pro',
      'boba' => 'pro',
      'game' => 'pro',
      'music' => 'pro',
      'book' => 'pro',
    ],
    // "name": the @username is drawn with the name's font and effect.
    'username_style' => [
      'default' => null,
      'name' => 'pro',
    ],
  ];

  /** Glyph of each OPTIONS['name_icon'] key, sent to the clients. */
  public const NAME_ICONS = [
    'fish' => '🐟',
    'cat' => '🐱',
    'dog' => '🐶',
    'frog' => '🐸',
    'panda' => '🐼',
    'fox' => '🦊',
    'penguin' => '🐧',
    'unicorn' => '🦄',
    'dragon' => '🐉',
    'octopus' => '🐙',
    'butterfly' => '🦋',
    'ghost' => '👻',
    'alien' => '👽',
    'robot' => '🤖',
    'rocket' => '🚀',
    'fire' => '🔥',
    'lightning' => '⚡',
    'star' => '⭐',
    'moon' => '🌙',
    'rainbow' => '🌈',
    'crown' => '👑',
    'gem' => '💎',
    'clover' => '🍀',
    'cactus' => '🌵',
    'sakura' => '🌸',
    'pizza' => '🍕',
    'boba' => '🧋',
    'game' => '🎮',
    'music' => '🎵',
    'book' => '📚',
  ];

  /**
   * Tier from which a display name may hold emoji and decorative Unicode
   * (𝓓𝓪𝔂𝓼, 𝟐𝟖...). Below it a new name is limited to ordinary letters.
   */
  public const FANCY_NAME_TIER = 'pro';

  /**
   * Name fonts the clients don't bundle: the files live in public/fonts/name
   * and are listed by GET /v1.0/name-fonts, so adding one here (plus its
   * OPTIONS['name_font'] entry and the .ttf) needs no client release. All
   * are static single-weight files with full Vietnamese glyphs, from Google
   * Fonts (Open Font License). Most also cover Cyrillic; Google Sans Flex,
   * Space Grotesk, Be Vietnam Pro and Quicksand do not, so Russian names in
   * those fall back to the system font.
   */
  public const SERVER_FONTS = [
    'flex' => ['label' => 'Google Sans Flex', 'file' => 'GoogleSansFlex.ttf'],
    'grotesk' => ['label' => 'Space Grotesk', 'file' => 'SpaceGrotesk.ttf'],
    'montserrat' => ['label' => 'Montserrat', 'file' => 'Montserrat.ttf'],
    'bevietnam' => ['label' => 'Be Vietnam Pro', 'file' => 'BeVietnamPro.ttf'],
    'nunito' => ['label' => 'Nunito', 'file' => 'Nunito.ttf'],
    'quicksand' => ['label' => 'Quicksand', 'file' => 'Quicksand.ttf'],
    'comfortaa' => ['label' => 'Comfortaa', 'file' => 'Comfortaa.ttf'],
    'manrope' => ['label' => 'Manrope', 'file' => 'Manrope.ttf'],
    'raleway' => ['label' => 'Raleway', 'file' => 'Raleway.ttf'],
    'exo' => ['label' => 'Exo 2', 'file' => 'Exo2.ttf'],
    'playfair' => ['label' => 'Playfair Display', 'file' => 'PlayfairDisplay.ttf'],
    'merriweather' => ['label' => 'Merriweather', 'file' => 'Merriweather.ttf'],
    'robotoslab' => ['label' => 'Roboto Slab', 'file' => 'RobotoSlab.ttf'],
    'lobster' => ['label' => 'Lobster', 'file' => 'Lobster.ttf'],
    'pacifico' => ['label' => 'Pacifico', 'file' => 'Pacifico.ttf'],
  ];

  /** Tier needed to keep an uploaded GIF avatar animated. */
  public const ANIMATED_AVATAR_TIER = 'veteran';

  /** Tier needed to pick a second colour (a gradient) for the theme colours. */
  public const GRADIENT_TIER = 'premium';

  private const COLOR_FIELDS = ['primary_color', 'accent_color', 'banner_color'];

  /**
   * Optional second colour of each COLOR_FIELDS entry: when set, that colour
   * is drawn as a gradient from the first to this one. Null = solid.
   */
  private const GRADIENT_FIELDS = ['primary_color_2', 'accent_color_2', 'banner_color_2'];

  private const HEX_COLOR = '/\A#[0-9a-fA-F]{6}\z/';

  /**
   * Validation rules for the `profile_theme` field of a profile update.
   */
  public static function rules(): array
  {
    $keys = implode(',', [...self::COLOR_FIELDS, ...self::GRADIENT_FIELDS, ...array_keys(self::OPTIONS), 'name_colors']);

    $rules = [
      'profile_theme' => 'nullable|array:' . $keys,
      'profile_theme.name_colors' => ['nullable', 'array', 'min:1', 'max:2'],
      'profile_theme.name_colors.*' => ['string', 'regex:' . self::HEX_COLOR],
    ];

    foreach ([...self::COLOR_FIELDS, ...self::GRADIENT_FIELDS] as $field) {
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

    // A second colour only means something next to a first one.
    foreach (self::GRADIENT_FIELDS as $index => $field) {
      $normalized[$field] = $normalized[self::COLOR_FIELDS[$index]] !== null
        ? $color($theme[$field] ?? null, null)
        : null;
    }

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

    if (!self::canUseGradientColors($user)) {
      foreach (self::GRADIENT_FIELDS as $field) {
        if (($theme[$field] ?? null) !== null) {
          $errors['profile_theme.' . $field] = [
            'Màu chuyển sắc cần đạt ' . self::tierMinPoints(self::GRADIENT_TIER) . ' điểm.',
          ];
        }
      }
    }

    return $errors;
  }

  public static function canUseFancyName(AuthAccount $user): bool
  {
    return self::tierReached($user, self::FANCY_NAME_TIER);
  }

  /**
   * Whether a display name only uses what every member may use: letters of
   * ordinary scripts (with their accents), digits, spaces and a little
   * punctuation. Emoji, symbols and the decorative alphabets (mathematical
   * 𝓓𝓪𝔂𝓼 / 𝟐𝟖, fullwidth, circled letters - which Unicode files under
   * "common" or as Latin look-alikes) are what FANCY_NAME_TIER unlocks.
   */
  public static function isPlainName(string $name): bool
  {
    if (preg_match('/[\x{FF00}-\x{FFEF}\x{2460}-\x{24FF}\x{1D00}-\x{1DBF}\x{2070}-\x{209F}\x{A720}-\x{A7FF}\x{AB30}-\x{AB6F}]/u', $name)) {
      return false;
    }

    return (bool) preg_match(
      '/\A[\p{Latin}\p{Cyrillic}\p{Greek}\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\p{Thai}\p{Mn}0-9 .,\x27\x{2019}_\-]+\z/u',
      $name
    );
  }

  /**
   * The error for a display name this user may not set yet, or null.
   */
  public static function nameError(AuthAccount $user, string $name): ?string
  {
    if (self::canUseFancyName($user) || self::isPlainName($name)) {
      return null;
    }

    return 'Tên chỉ được dùng chữ cái, chữ số và dấu cách. Biểu tượng cảm xúc và ký tự đặc biệt cần đạt '
      . self::tierMinPoints(self::FANCY_NAME_TIER) . ' điểm.';
  }

  public static function canUseGradientColors(AuthAccount $user): bool
  {
    return self::canCustomize($user) && self::tierReached($user, self::GRADIENT_TIER);
  }

  /**
   * The server-hosted name fonts, as GET /v1.0/name-fonts returns them.
   */
  public static function serverFonts(): array
  {
    $base = rtrim((string) config('app.url'), '/') . '/v1.0/name-fonts/';
    $requiredPoints = fn($key) => self::tierMinPoints(self::OPTIONS['name_font'][$key] ?? null);

    return collect(self::SERVER_FONTS)->map(fn($font, $key) => [
      'key' => $key,
      'label' => $font['label'],
      // Unique per file, so a client can register it under this name.
      'family' => 'CYO ' . pathinfo($font['file'], PATHINFO_FILENAME),
      'url' => $base . $font['file'],
      'required_points' => $requiredPoints($key),
    ])->values()->all();
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

    if (!self::canUseGradientColors($user)) {
      foreach (self::GRADIENT_FIELDS as $field) {
        $theme[$field] = null;
      }
    }

    // Derived, never stored: saves every client a table of glyphs.
    $theme['name_icon_emoji'] = self::NAME_ICONS[$theme['name_icon']] ?? null;
    // "tier_veteran" -> "veteran": draw that tier's icon instead of a glyph.
    $theme['name_icon_tier'] = str_starts_with($theme['name_icon'], 'tier_')
      ? substr($theme['name_icon'], 5)
      : null;

    return $theme;
  }

  /**
   * The part of forDisplay() shown next to a user's name in posts, comments
   * and lists: the name style and the avatar frame (plus the theme colors
   * the "theme" frame is drawn with). Profile effects/frames, banner etc.
   * only belong on the profile page itself.
   */
  public static function forAuthor(?AuthAccount $user): ?array
  {
    $theme = $user ? self::forDisplay($user) : null;
    // The member's tier travels with the theme, so every place that shows a
    // name can draw the tier's icon after it (the default when the user
    // picked no icon of their own) - also for members with no saved theme,
    // who get an object holding nothing else.
    $tierId = $user ? ($user->getMemberTier()['id'] ?? null) : null;
    if ($theme === null) {
      return $tierId ? ['member_tier' => $tierId] : null;
    }

    return ['member_tier' => $tierId] + array_intersect_key($theme, array_flip([
      'primary_color',
      'accent_color',
      'primary_color_2',
      'accent_color_2',
      'name_font',
      'name_effect',
      'name_colors',
      'avatar_frame',
      'name_icon',
      'name_icon_emoji',
      'name_icon_tier',
      'username_style',
    ]));
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
        // Server-hosted fonts carry their display name; the clients have
        // their own labels for everything else.
        ...($field === 'name_font' && isset(self::SERVER_FONTS[$key])
          ? ['label' => self::SERVER_FONTS[$key]['label']]
          : []),
        ...($field === 'name_icon' ? ['icon' => self::NAME_ICONS[$key] ?? null, 'tier' => str_starts_with($key, 'tier_') ? substr($key, 5) : null] : []),
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
      // Second colour (gradient) for primary / accent / banner colours.
      'color_gradient' => [
        'required_points' => self::tierMinPoints(self::GRADIENT_TIER),
        'unlocked' => self::canUseGradientColors($user),
      ],
      // Emoji and decorative Unicode letters in the display name.
      'fancy_name' => [
        'required_points' => self::tierMinPoints(self::FANCY_NAME_TIER),
        'unlocked' => self::canUseFancyName($user),
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
