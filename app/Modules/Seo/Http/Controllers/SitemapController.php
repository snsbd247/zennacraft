<?php

namespace App\Modules\Seo\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Seo\Services\SitemapService;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __construct(private SitemapService $sitemapService) {}

    public function index(): Response
    {
        return response($this->sitemapService->xml(), 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
