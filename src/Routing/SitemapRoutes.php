<?php

namespace Syndicate\Promoter\Routing;

use Illuminate\Support\Facades\Route;
use Syndicate\Promoter\Contracts\HasSeo;
use Syndicate\Promoter\Controllers\SitemapController;
use Syndicate\Promoter\Sitemaps\IndexSitemap;
use Syndicate\Promoter\Sitemaps\ModelSitemap;

class SitemapRoutes
{
    public static function register(IndexSitemap $sitemap): void
    {
        $sitemap->validateModels();

        $prefix = $sitemap->prefix;
        $middleware = $sitemap->middleware;
        $indexSlug = static::addXmlEnding($sitemap->indexSlug);
        $newsSlug = static::addXmlEnding($sitemap->newsSlug);
        $models = $sitemap->getAllModels();

        Route::prefix($prefix)
            ->middleware($middleware)
            ->group(function () use ($models, $indexSlug, $newsSlug, $sitemap) {
                // Register sitemap index route
                Route::get($indexSlug, [SitemapController::class, 'index'])
                    ->setDefaults([
                        'sitemapClass' => $sitemap::class,
                    ])
                    ->name($sitemap->indexRouteName());

                // Register model-specific sitemap routes
                foreach ($models as $modelClass) {
                    if (!self::isValidSitemapModel($modelClass)) {
                        continue;
                    }

                    /** @var ModelSitemap $modelSitemap */
                    $modelSitemap = $modelClass::sitemap();
                    $routePath = static::addXmlEnding($modelSitemap->sitemapSlug);

                    Route::get($routePath, [SitemapController::class, 'show'])
                        ->setDefaults([
                            'modelClass' => $modelClass,
                        ])
                        ->name($modelSitemap->routeName());
                }

                // Register a separate news sitemap route if there are news models
                $newsModels = $sitemap->newsModels;
                if (!empty($newsModels)) {
                    Route::get($newsSlug, [SitemapController::class, 'showNews'])
                        ->setDefaults([
                            'sitemapClass' => $sitemap::class,
                        ])
                        ->name($sitemap->newsRouteName());
                }
            });
    }

    public static function addXmlEnding(string $routePath): string
    {
        if (str_ends_with($routePath, '.xml')) {
            return $routePath;
        }

        return $routePath.'.xml';
    }

    private static function isValidSitemapModel(string $modelClass): bool
    {
        return class_exists($modelClass) &&
            is_subclass_of($modelClass, HasSeo::class);
    }
}
