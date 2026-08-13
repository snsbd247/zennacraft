<?php

namespace App\Modules\Seo\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function index(): Response
    {
        $baseUrl = rtrim(config('app.url') ?: url('/'), '/');

        $content = implode(PHP_EOL, [
            'User-agent: *',
            'Allow: /',
            'Disallow: /studio',
            'Disallow: /customer',
            'Disallow: /checkout',
            'Disallow: /track',
            'Sitemap: '.$baseUrl.'/sitemap.xml',
            '',
        ]);

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
