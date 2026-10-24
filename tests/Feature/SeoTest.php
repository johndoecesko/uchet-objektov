<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** SEO и Метрика: robots, sitemap, теги превью, микроразметка; счётчик — только на публичных страницах и только после согласия */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_opens_public_pages_and_closes_service_ones(): void
    {
        $robots = $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8')->getContent();
        $this->assertDoesNotMatchRegularExpression('#^Disallow: /$#m', $robots, 'сайт не закрыт целиком');
        foreach (['/app', '/platform', '/auth/', '/invite/', '/go/', '/livewire/', '/api/'] as $path) {
            $this->assertStringContainsString('Disallow: '.$path."\n", $robots);
        }
        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $robots);
    }

    public function test_sitemap_lists_public_pages(): void
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
        foreach (['/', '/install', '/terms', '/privacy', '/consent'] as $path) {
            $this->assertStringContainsString('<loc>'.url($path).'</loc>', $xml);
        }
        $this->assertStringNotContainsString('/app', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_landing_has_canonical_preview_tags_and_structured_data(): void
    {
        config(['services.yandex_verification' => '7f350f69dc39665b']);
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="'.url('/').'">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="'.url('/img/og.png').'">', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Финпрораб — учёт денег по монтажным объектам без Excel">', $html);
        $this->assertStringContainsString('<meta name="yandex-verification" content="7f350f69dc39665b">', $html);
        $this->assertSame(1, substr_count($html, '<h1'));

        preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $m);
        $ld = json_decode($m[1] ?? '', true);
        $this->assertSame('WebApplication', $ld['@type'] ?? null);
        $this->assertSame('0', $ld['offers']['price'] ?? null);
    }

    public function test_service_pages_are_not_indexed(): void
    {
        $this->get('/app/login')->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $admin = User::create(['name' => 'А', 'email' => 'a@test.ru', 'password' => 'secret123']);
        $org = Organization::create(['name' => 'Орг']);
        $org->addMember($admin, UserRole::Admin);
        $invite = (new Invitation(['role' => UserRole::Foreman, 'expires_at' => now()->addDay()]))->forceFill(['organization_id' => $org->id, 'created_by' => $admin->id]);
        $invite->save();
        $this->get($invite->url())->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertDontSee('rel="canonical"', false);
    }

    public function test_metrika_only_on_public_pages_and_only_after_consent(): void
    {
        $this->get('/')->assertDontSee('cookie-note', false)->assertDontSee('mc.yandex.ru', false);

        config(['services.yandex_metrika.id' => '113772210']);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('id="cookie-note" hidden', $html, 'окно согласия');
        $this->assertStringContainsString('Настройки cookie', $html);
        $this->assertStringContainsString('ID = 113772210', $html);
        // счётчик не подключён тегом — его грузит скрипт только после «Принять»
        $this->assertDoesNotMatchRegularExpression('/<script[^>]+src="https?:/i', $html);
        $this->assertStringNotContainsString('<noscript>', $html);

        foreach (['/install', '/privacy', '/terms', '/consent'] as $url) {
            $this->get($url)->assertSee('cookie-note', false);
        }
        $this->get('/app/login')->assertDontSee('cookie-note', false)->assertDontSee('mc.yandex.ru', false);
        $this->get('/privacy')->assertSee('Яндекс Метрика')->assertSee('id="cookie"', false);
    }

    public function test_error_pages_are_russian(): void
    {
        $this->get('/net-takoy-stranicy')->assertNotFound()->assertSee('Страница не найдена')->assertDontSee('Not Found');
    }
}
