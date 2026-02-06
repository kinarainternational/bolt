<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_disabled()
    {
        // Registration is disabled - only admins can add users
        $response = $this->get('/register');

        $response->assertNotFound();
    }

    public function test_users_cannot_register_directly()
    {
        // Registration is disabled - only admins can add users
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertNotFound();
        $this->assertGuest();
    }
}
