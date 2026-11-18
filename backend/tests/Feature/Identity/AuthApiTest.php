<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'MotDePasse2026';

    public function test_a_visitor_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Camille Martin',
            'email' => '  Camille@Example.FR ',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'phone' => '+33 6 12 34 56 78',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'camille@example.fr')
            ->assertJsonPath('data.email_verified', false)
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonMissingPath('data.password');

        $user = User::query()->sole();
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertStringStartsWith('$argon2id$', $user->password, 'Argon2id exigé par le cahier des charges §6.2');
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_registration_validates_input(): void
    {
        User::factory()->create(['email' => 'pris@example.fr']);

        $this->postJson('/api/v1/auth/register', [
            'name' => '',
            'email' => 'pris@example.fr',
            'password' => 'court',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_a_user_can_log_in_access_their_profile_and_log_out(): void
    {
        User::factory()->create(['email' => 'camille@example.fr', 'password' => self::PASSWORD]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'Camille@example.fr',
            'password' => self::PASSWORD,
            'device_name' => 'iPhone',
        ])->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'camille@example.fr');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_wrong_credentials_are_rejected_without_revealing_whether_the_account_exists(): void
    {
        User::factory()->create(['email' => 'camille@example.fr', 'password' => self::PASSWORD]);

        $wrongPassword = $this->postJson('/api/v1/auth/login', ['email' => 'camille@example.fr', 'password' => 'mauvais'])
            ->assertUnprocessable()->json('errors.email');
        $unknownEmail = $this->postJson('/api/v1/auth/login', ['email' => 'inconnu@example.fr', 'password' => 'mauvais'])
            ->assertUnprocessable()->json('errors.email');

        $this->assertSame($wrongPassword, $unknownEmail);
    }

    public function test_login_is_rate_limited_against_brute_force(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'cible@example.fr', 'password' => 'essai'.$i])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'cible@example.fr', 'password' => 'essai6'])->assertTooManyRequests();
    }

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }
}
