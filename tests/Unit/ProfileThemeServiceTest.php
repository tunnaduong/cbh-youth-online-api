<?php

namespace Tests\Unit;

use App\Models\AuthAccount;
use App\Models\UserProfile;
use App\Services\ProfileThemeService;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ProfileThemeServiceTest extends TestCase
{
    /**
     * In-memory user (never saved) with the given points and saved theme.
     */
    private function user(int $points, ?array $theme = null): AuthAccount
    {
        $user = new AuthAccount(['points' => $points]);
        $user->setRelation('profile', new UserProfile(['profile_theme' => $theme]));

        return $user;
    }

    private function passes(array $theme): bool
    {
        return Validator::make(['profile_theme' => $theme], ProfileThemeService::rules())->passes();
    }

    public function test_customizing_needs_the_trainee_tier(): void
    {
        $this->assertFalse(ProfileThemeService::canCustomize($this->user(49)));
        $this->assertTrue(ProfileThemeService::canCustomize($this->user(50)));
    }

    public function test_rules_accept_a_valid_theme(): void
    {
        $this->assertTrue($this->passes([
            'primary_color' => '#FF0080',
            'accent_color' => '#00ffaa',
            'banner_color' => '#123456',
            'name_font' => 'gothic',
            'name_effect' => 'gradient',
            'name_colors' => ['#ff0080', '#00FFAA'],
            'avatar_frame' => 'veteran',
            'profile_effect' => 'snow',
            'profile_frame' => 'gold',
        ]));
        $this->assertTrue($this->passes(['name_effect' => 'neon', 'name_colors' => ['#ff0080']]));
        $this->assertTrue($this->passes(['primary_color' => null, 'banner_color' => null]));
    }

    public function test_rules_reject_bad_colors_unknown_options_and_extra_keys(): void
    {
        $this->assertFalse($this->passes(['primary_color' => 'red']));
        $this->assertFalse($this->passes(['banner_color' => '#fff']));
        $this->assertFalse($this->passes(['primary_color' => '#ffffff;background:url(x)']));
        $this->assertFalse($this->passes(['primary_color' => "#ffffff\n"]));
        $this->assertFalse($this->passes(['name_font' => 'comic-sans']));
        $this->assertFalse($this->passes(['name_effect' => 'sparkle']));
        $this->assertFalse($this->passes(['avatar_frame' => '../secret']));
        $this->assertFalse($this->passes(['profile_effect' => 'fireworks']));
        $this->assertFalse($this->passes(['profile_frame' => 'diamond']));
        $this->assertFalse($this->passes(['name_colors' => ['red']]));
        $this->assertFalse($this->passes(['name_colors' => ['#111111', '#222222', '#333333']]));
        $this->assertFalse($this->passes(['name_colors' => []]));
        $this->assertFalse($this->passes(['custom_css' => 'body{}']));
        $this->assertFalse($this->passes(['name_style' => 'gradient']));
    }

    public function test_normalize_fills_defaults_and_lowercases_colors(): void
    {
        $this->assertSame([
            'primary_color' => '#ff0080',
            'accent_color' => null,
            'banner_color' => null,
            'name_font' => 'default',
            'name_effect' => 'none',
            'avatar_frame' => 'none',
            'profile_effect' => 'none',
            'profile_frame' => 'none',
            'name_icon' => 'none',
            'username_font' => 'default',
            'username_effect' => 'none',
            'username_style' => 'default',
            'name_colors' => [ProfileThemeService::DEFAULT_PRIMARY, ProfileThemeService::DEFAULT_ACCENT],
            'username_colors' => [ProfileThemeService::DEFAULT_PRIMARY, ProfileThemeService::DEFAULT_ACCENT],
            'primary_color_2' => null,
            'accent_color_2' => null,
            'banner_color_2' => null,
        ], ProfileThemeService::normalize(['primary_color' => '#FF0080', 'junk' => 'x']));

        // A second colour is kept next to its first colour, dropped without one.
        $gradient = ProfileThemeService::normalize([
            'primary_color' => '#ff0080',
            'primary_color_2' => '#00FFAA',
            'banner_color_2' => '#123456',
        ]);
        $this->assertSame('#00ffaa', $gradient['primary_color_2']);
        $this->assertNull($gradient['banner_color_2']);

        $this->assertSame(
            ['#abcdef', ProfileThemeService::DEFAULT_ACCENT],
            ProfileThemeService::normalize(['name_colors' => ['#ABCDEF']])['name_colors']
        );
    }

    public function test_display_is_null_without_a_saved_theme(): void
    {
        $this->assertNull(ProfileThemeService::forDisplay($this->user(5000)));
    }

    public function test_display_hides_the_theme_when_the_base_privilege_is_lost_but_keeps_it_saved(): void
    {
        $user = $this->user(49, ['primary_color' => '#ff0080', 'avatar_frame' => 'theme']);

        $this->assertNull(ProfileThemeService::forDisplay($user));
        $this->assertSame('#ff0080', ProfileThemeService::editorState($user)['saved']['primary_color']);
        $this->assertFalse(ProfileThemeService::editorState($user)['can_customize']);
    }

    public function test_display_drops_only_the_options_above_the_current_tier(): void
    {
        $saved = [
            'banner_color' => '#101010',
            'name_font' => 'gothic',          // distinguished (500)
            'name_effect' => 'gradient',      // active (150)
            'avatar_frame' => 'veteran',      // veteran (1000)
            'profile_effect' => 'sparkles',   // active (150)
            'profile_frame' => 'gold',        // distinguished (500)
        ];

        $at150 = ProfileThemeService::forDisplay($this->user(150, $saved));
        $this->assertSame('#101010', $at150['banner_color']);
        $this->assertSame('default', $at150['name_font']);
        $this->assertSame('gradient', $at150['name_effect']);
        $this->assertSame('none', $at150['avatar_frame']);
        $this->assertSame('sparkles', $at150['profile_effect']);
        $this->assertSame('none', $at150['profile_frame']);

        $at1000 = ProfileThemeService::forDisplay($this->user(1000, $saved));
        $this->assertSame('gothic', $at1000['name_font']);
        $this->assertSame('veteran', $at1000['avatar_frame']);
        $this->assertSame('gold', $at1000['profile_frame']);
    }

    public function test_display_survives_corrupt_and_old_saved_values(): void
    {
        $user = $this->user(50, [
            'primary_color' => 'javascript:alert(1)',
            'avatar_frame' => 'gone',
            'name_font' => 'removed-font',
            'name_colors' => 'not-an-array',
            // Old shape from before fonts/effects existed - ignored.
            'name_style' => 'gradient',
        ]);

        $theme = ProfileThemeService::forDisplay($user);

        $this->assertNull($theme['primary_color']);
        $this->assertSame('none', $theme['avatar_frame']);
        $this->assertSame('default', $theme['name_font']);
        $this->assertSame('none', $theme['name_effect']);
        $this->assertArrayNotHasKey('name_style', $theme);
        $this->assertSame([ProfileThemeService::DEFAULT_PRIMARY, ProfileThemeService::DEFAULT_ACCENT], $theme['name_colors']);
    }

    public function test_locked_errors_follow_tiers(): void
    {
        $theme = ProfileThemeService::normalize([
            'name_font' => 'gothic',
            'name_effect' => 'pop',
            'avatar_frame' => 'trainee',
            'profile_frame' => 'neon',
        ]);

        $this->assertSame([], ProfileThemeService::lockedErrors($this->user(1000), $theme));
        $this->assertSame(
            ['profile_theme.name_font', 'profile_theme.profile_frame'],
            array_keys(ProfileThemeService::lockedErrors($this->user(150), $theme))
        );
        $this->assertSame(
            ['Tùy chọn này cần đạt 1000 điểm.'],
            ProfileThemeService::lockedErrors($this->user(150), $theme)['profile_theme.profile_frame']
        );
    }

    public function test_author_theme_only_carries_the_name_and_avatar_options(): void
    {
        $user = $this->user(1000, [
            'name_font' => 'pixel',
            'name_effect' => 'neon',
            'avatar_frame' => 'veteran',
            'profile_effect' => 'snow',
            'profile_frame' => 'gold',
            'banner_color' => '#000000',
        ]);

        $this->assertSame(
            [
                'member_tier', 'primary_color', 'accent_color', 'name_font', 'name_effect', 'avatar_frame', 'name_icon',
                'username_font', 'username_effect', 'username_style', 'name_colors', 'username_colors',
                'primary_color_2', 'accent_color_2', 'avatar_frame_url', 'name_icon_emoji', 'name_icon_tier',
            ],
            array_keys(ProfileThemeService::forAuthor($user))
        );
        $this->assertSame('veteran', ProfileThemeService::forAuthor($user)['avatar_frame']);
        $this->assertNull(ProfileThemeService::forAuthor($this->user(49, ['name_effect' => 'solid'])));
        $this->assertNull(ProfileThemeService::forAuthor(null));
        // A member with no saved theme still carries their tier.
        $this->assertSame(['member_tier' => 'active'], ProfileThemeService::forAuthor($this->user(150)));
    }

    public function test_custom_frames_need_the_pro_plus_tier_and_an_uploaded_image(): void
    {
        $theme = ProfileThemeService::normalize(['avatar_frame' => 'custom', 'profile_frame' => 'custom']);
        $withImages = function (int $points) {
            $user = $this->user($points, ['avatar_frame' => 'custom', 'profile_frame' => 'custom']);
            $user->profile->custom_avatar_frame = 'frames/avatar/1_a.png';
            $user->profile->custom_profile_frame = 'frames/profile/1_b.png';

            return $user;
        };

        $this->assertTrue($this->passes(['avatar_frame' => 'custom', 'profile_frame' => 'custom']));
        // A client can't name the image itself.
        $this->assertFalse($this->passes(['avatar_frame_url' => 'https://example.com/x.png']));

        $this->assertFalse(ProfileThemeService::canUseCustomFrames($this->user(2249)));
        $this->assertTrue(ProfileThemeService::canUseCustomFrames($this->user(2250)));

        // Below the tier: locked. At the tier without an image: upload first.
        $this->assertSame(
            ['Tùy chọn này cần đạt 2250 điểm.'],
            ProfileThemeService::lockedErrors($withImages(2000), $theme)['profile_theme.avatar_frame']
        );
        $this->assertSame(
            ['Hãy tải ảnh khung lên trước.'],
            ProfileThemeService::lockedErrors($this->user(2250), $theme)['profile_theme.profile_frame']
        );
        $this->assertSame([], ProfileThemeService::lockedErrors($withImages(2250), $theme));

        // Shown with the image's address; the profile one stays off the author theme.
        $shown = ProfileThemeService::forDisplay($withImages(2250));
        $this->assertSame('custom', $shown['avatar_frame']);
        $this->assertStringEndsWith('/storage/frames/avatar/1_a.png', $shown['avatar_frame_url']);
        $this->assertStringEndsWith('/storage/frames/profile/1_b.png', $shown['profile_frame_url']);
        $author = ProfileThemeService::forAuthor($withImages(2250));
        $this->assertSame('pro_plus', $author['member_tier']);
        $this->assertStringEndsWith('/storage/frames/avatar/1_a.png', $author['avatar_frame_url']);
        $this->assertArrayNotHasKey('profile_frame_url', $author);

        // Points dropped, or the image was removed: no frame, no address.
        foreach ([$withImages(2249), $this->user(2250, ['avatar_frame' => 'custom'])] as $user) {
            $hidden = ProfileThemeService::forDisplay($user);
            $this->assertSame('none', $hidden['avatar_frame']);
            $this->assertNull($hidden['avatar_frame_url']);
        }

        $state = ProfileThemeService::editorState($withImages(2000));
        $this->assertSame(2250, $state['custom_frames']['required_points']);
        $this->assertFalse($state['custom_frames']['unlocked']);
        $this->assertStringEndsWith('/storage/frames/avatar/1_a.png', $state['custom_frames']['avatar_url']);
        $this->assertContains(['key' => 'custom', 'required_points' => 2250, 'unlocked' => false], $state['options']['profile_frame']);
    }

    public function test_animated_avatar_needs_the_veteran_tier(): void
    {
        $this->assertFalse(ProfileThemeService::canUseAnimatedAvatar($this->user(999)));
        $this->assertTrue(ProfileThemeService::canUseAnimatedAvatar($this->user(1000)));
    }

    public function test_editor_state_lists_points_needed_per_option(): void
    {
        $state = ProfileThemeService::editorState($this->user(150));

        $this->assertSame(50, $state['required_points']);
        $this->assertSame(150, $state['current_points']);
        $this->assertSame(['trainee', 'active', 'distinguished', 'veteran', 'premium', 'pro', 'pro_plus'], array_column($state['tiers'], 'id'));
        $this->assertSame([true, true, false, false, false, false, false], array_column($state['tiers'], 'reached'));
        $this->assertContains(['key' => 'rainbow', 'required_points' => 1500, 'unlocked' => false], $state['options']['name_effect']);
        $this->assertContains(['key' => 'outline', 'required_points' => 1500, 'unlocked' => false], $state['options']['name_effect']);
        // Server-hosted fonts are premium and carry their label.
        $this->assertContains(
            ['key' => 'flex', 'required_points' => 1500, 'unlocked' => false, 'label' => 'Google Sans Flex'],
            $state['options']['name_font']
        );
        $this->assertSame(['required_points' => 1500, 'unlocked' => false], $state['color_gradient']);
        $this->assertTrue(ProfileThemeService::editorState($this->user(1500))['color_gradient']['unlocked']);

        // Every server font has a tier and a file on disk.
        foreach (ProfileThemeService::SERVER_FONTS as $key => $font) {
            $this->assertArrayHasKey($key, ProfileThemeService::OPTIONS['name_font']);
            $this->assertFileExists(dirname(__DIR__, 2) . '/public/fonts/name/' . $font['file']);
        }
        $this->assertSame(['required_points' => 1000, 'unlocked' => false], $state['animated_avatar']);
        $this->assertContains(['key' => 'none', 'required_points' => 50, 'unlocked' => true], $state['options']['profile_effect']);
        $this->assertContains(['key' => 'sparkles', 'required_points' => 150, 'unlocked' => true], $state['options']['profile_effect']);
        $this->assertContains(['key' => 'aurora', 'required_points' => 1000, 'unlocked' => false], $state['options']['profile_effect']);

        // Below the base tier nothing is unlocked, not even the defaults.
        $locked = ProfileThemeService::editorState($this->user(0));
        $this->assertContains(['key' => 'none', 'required_points' => 50, 'unlocked' => false], $locked['options']['avatar_frame']);
    }
}
