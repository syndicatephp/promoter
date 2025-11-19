<?php

namespace Syndicate\Promoter\Controllers;

use Illuminate\Http\Response;
use Syndicate\Promoter\Contracts\HasSeo;
use Syndicate\Promoter\Sitemaps\IndexSitemap;

class SitemapController
{
    /**
     * Generate sitemap index
     * @param  class-string<IndexSitemap>  $sitemapClass
     * @return Response
     */
    public function index(string $sitemapClass): Response
    {
        return $this->response($sitemapClass::make()->getIndexContent());
    }

    private function response(string $xml): Response
    {
        return response($xml, 200)
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Generate sitemap for a specific HasSeo model
     * @param  class-string<HasSeo>  $modelClass
     * @return Response
     */
    public function show(string $modelClass): Response
    {
        return $this->response($modelClass::sitemap()->getContent());
    }

    /**
     * Generate sitemap news
     * @param  class-string<IndexSitemap>  $sitemapClass
     * @return Response
     */
    public function showNews(string $sitemapClass): Response
    {
        return $this->response($sitemapClass::make()->getNewsContent());
    }
}
