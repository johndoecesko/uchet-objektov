<?php

namespace Tests\Feature;

use Tests\TestCase;

class CanonicalHostTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://finprorab.ru', 'app.force_canonical_host' => true]);
    }

    public function test_old_host_redirects_with_path_and_query(): void
    {
        $this->get('http://uchet.easat.ru/projects/5?tab=2')
            ->assertStatus(301)
            ->assertRedirect('https://finprorab.ru/projects/5?tab=2');
    }

    public function test_www_redirects_to_bare_domain(): void
    {
        $this->get('https://www.finprorab.ru/login')->assertRedirect('https://finprorab.ru/login');
    }

    public function test_canonical_host_is_served(): void
    {
        $this->get('https://finprorab.ru/login')->assertOk();
    }

    public function test_post_is_not_redirected(): void
    {
        // вебхук MAX со старого адреса: без секрета — 403, но не 301
        $this->postJson('http://uchet.easat.ru/api/max/webhook', [])->assertForbidden();
    }

    public function test_disabled_by_default(): void
    {
        config(['app.force_canonical_host' => false]);
        $this->get('http://uchet.easat.ru/login')->assertOk();
    }
}
