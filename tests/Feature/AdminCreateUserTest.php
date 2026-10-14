<?php

namespace Tests\Feature;

use App\Models\AuthAccount;
use App\Models\UserContent;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCreateUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_admin_creates_account_with_generated_password_and_avatar(): void
    {
        Sanctum::actingAs(AuthAccount::factory()->create(['role' => 'admin']));

        $response = $this->postJson('/api/v1.0/admin/users', [
            'username' => 'seed_user01',
            'name' => 'Nguyễn Văn Seed',
            'avatar' => UploadedFile::fake()->image('avatar.png', 600, 400),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.email', 'seed_user01@tunnaduong.com')
            ->assertJsonPath('user.profile_name', 'Nguyễn Văn Seed');

        $password = $response->json('password');
        $this->assertIsString($password);
        $this->assertGreaterThanOrEqual(12, strlen($password));

        $account = AuthAccount::where('username', 'seed_user01')->firstOrFail();
        $this->assertTrue(Hash::check($password, $account->password));
        $this->assertNotNull($account->email_verified_at, 'Manual accounts skip email verification.');

        $profile = UserProfile::where('auth_account_id', $account->id)->firstOrFail();
        $this->assertSame('Nguyễn Văn Seed', $profile->profile_name);
        $this->assertNotNull($profile->profile_picture);

        $content = UserContent::findOrFail($profile->profile_picture);
        $this->assertSame('image/jpeg', $content->file_type);
        Storage::disk('public')->assertExists($content->file_path);
    }

    public function test_typed_password_is_used_and_not_returned(): void
    {
        Sanctum::actingAs(AuthAccount::factory()->create(['role' => 'admin']));

        $response = $this->postJson('/api/v1.0/admin/users', [
            'username' => 'seed_user02',
            'name' => 'Seed 2',
            'password' => 'matkhau123',
        ]);

        $response->assertStatus(201)->assertJsonPath('password', null);

        $account = AuthAccount::where('username', 'seed_user02')->firstOrFail();
        $this->assertTrue(Hash::check('matkhau123', $account->password));
        $this->assertNull(UserProfile::where('auth_account_id', $account->id)->first()->profile_picture);
    }

    public function test_duplicate_username_or_email_is_rejected(): void
    {
        Sanctum::actingAs(AuthAccount::factory()->create(['role' => 'admin']));
        AuthAccount::factory()->create(['username' => 'taken_name', 'email' => 'someone@example.com']);

        $this->postJson('/api/v1.0/admin/users', ['username' => 'taken_name', 'name' => 'X'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['username']);

        // Left over from an earlier account that used the same address.
        AuthAccount::factory()->create(['username' => 'old_name', 'email' => 'clash@tunnaduong.com']);
        $this->postJson('/api/v1.0/admin/users', ['username' => 'clash', 'name' => 'X'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    public function test_non_staff_cannot_create_accounts(): void
    {
        Sanctum::actingAs(AuthAccount::factory()->create(['role' => 'user']));

        $this->postJson('/api/v1.0/admin/users', ['username' => 'sneaky', 'name' => 'X'])
            ->assertStatus(403);

        $this->assertDatabaseMissing('cyo_auth_accounts', ['username' => 'sneaky']);
    }
}
