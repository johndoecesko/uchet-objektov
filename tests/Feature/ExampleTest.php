<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()
            ->assertSee('<link rel="manifest" href="/manifest.json">', false)
            ->assertSee("navigator.serviceWorker.register('/sw.js')", false);
        $this->assertFileExists(public_path('manifest.json'));
        $this->assertFileExists(public_path('icons/maskable-512.png'));
        $this->assertJson(file_get_contents(public_path('manifest.json')));
    }
}
