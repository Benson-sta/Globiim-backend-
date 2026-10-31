<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_email_and_account_type(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test Creator',
            'username' => 'testcreator',
            'email' => 'creator@example.com',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'account_type' => 'creator',
            'device_name' => 'test-device',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.username', 'testcreator')
            ->assertJsonPath('user.account_type', 'creator')
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'username', 'email']]);

        $this->assertDatabaseHas('users', ['email' => 'creator@example.com', 'account_type' => 'creator']);
    }

    public function test_registration_requires_a_valid_account_type(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'user@example.com',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'account_type' => 'admin',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['account_type']);
    }

    public function test_registered_user_can_login_and_access_me_endpoint(): void
    {
        User::create([
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'user@example.com',
            'password' => 'StrongPass123!',
            'account_type' => 'individual',
            'status' => 'active',
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'login' => 'user@example.com',
            'password' => 'StrongPass123!',
        ]);

        $login->assertOk()->assertJsonStructure(['token', 'user']);

        $token = $login->json('token');
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.username', 'testuser');
    }
}
