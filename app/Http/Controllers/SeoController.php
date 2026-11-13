<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * robots.txt и sitemap.xml — от APP_URL, чтобы у каждой установки был свой адрес.
 * Индексируются только публичные страницы; учёт, вход, приглашения и служебные адреса закрыты.
 */
class SeoController extends Controller
{
    /** Публичные страницы: адрес → [частота обновления, приоритет] */
    private const PAGES = [
        '/' => ['weekly', '1.0'],
        '/install' => ['monthly', '0.6'],
        '/terms' => ['yearly', '0.3'],
        '/privacy' => ['yearly', '0.3'],
        '/consent' => ['yearly', '0.2'],
    ];

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /app',
            'Disallow: /platform',
            'Disallow: /auth/',
            'Disallow: /invite/',
            'Disallow: /go/',
            'Disallow: /livewire/',
            'Disallow: /api/',
            'Disallow: /demo',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function sitemap(): Response
    {
        $lastmod = max(config('releases.0.date'), config('legal.version'));
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach (self::PAGES as $path => [$freq, $priority]) {
            $xml .= '  <url><loc>'.e(url($path)).'</loc><lastmod>'.$lastmod.'</lastmod><changefreq>'.$freq.'</changefreq><priority>'.$priority.'</priority></url>'."\n";
        }

        return response($xml.'</urlset>'."\n", 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
