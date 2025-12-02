<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $baseUrl = config('app.url', 'https://easeverifier.com');

        // Static pages with their priority and change frequency
        $staticPages = [
            ['url' => '/', 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['url' => '/services', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['url' => '/pricing', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['url' => '/documentation', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['url' => '/about', 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['url' => '/blog', 'priority' => '0.8', 'changefreq' => 'daily'],
            ['url' => '/contact', 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['url' => '/privacy', 'priority' => '0.3', 'changefreq' => 'yearly'],
            ['url' => '/terms', 'priority' => '0.3', 'changefreq' => 'yearly'],
            ['url' => '/cookies', 'priority' => '0.3', 'changefreq' => 'yearly'],
            ['url' => '/login', 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['url' => '/register', 'priority' => '0.6', 'changefreq' => 'monthly'],
        ];

        // Get published blog posts
        $blogPosts = BlogPost::published()
            ->select('slug', 'updated_at')
            ->orderBy('published_at', 'desc')
            ->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

        // Add static pages
        foreach ($staticPages as $page) {
            $xml .= '  <url>' . PHP_EOL;
            $xml .= '    <loc>' . $baseUrl . $page['url'] . '</loc>' . PHP_EOL;
            $xml .= '    <lastmod>' . now()->toDateString() . '</lastmod>' . PHP_EOL;
            $xml .= '    <changefreq>' . $page['changefreq'] . '</changefreq>' . PHP_EOL;
            $xml .= '    <priority>' . $page['priority'] . '</priority>' . PHP_EOL;
            $xml .= '  </url>' . PHP_EOL;
        }

        // Add blog posts
        foreach ($blogPosts as $post) {
            $xml .= '  <url>' . PHP_EOL;
            $xml .= '    <loc>' . $baseUrl . '/blog/' . $post->slug . '</loc>' . PHP_EOL;
            $xml .= '    <lastmod>' . $post->updated_at->toDateString() . '</lastmod>' . PHP_EOL;
            $xml .= '    <changefreq>weekly</changefreq>' . PHP_EOL;
            $xml .= '    <priority>0.7</priority>' . PHP_EOL;
            $xml .= '  </url>' . PHP_EOL;
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }

    public function robots(): Response
    {
        $baseUrl = config('app.url', 'https://easeverifier.com');

        $robots = "User-agent: *\n";
        $robots .= "Allow: /\n";
        $robots .= "Disallow: /admin/\n";
        $robots .= "Disallow: /customer/\n";
        $robots .= "Disallow: /settings/\n";
        $robots .= "Disallow: /api/\n";
        $robots .= "\n";
        $robots .= "Sitemap: {$baseUrl}/sitemap.xml\n";

        return response($robots, 200, [
            'Content-Type' => 'text/plain',
        ]);
    }
}

