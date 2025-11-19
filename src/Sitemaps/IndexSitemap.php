<?php

namespace Syndicate\Promoter\Sitemaps;

use InvalidArgumentException;
use Syndicate\Promoter\Contracts\HasSeo;
use Syndicate\Promoter\Services\SitemapService;

/**
 * SitemapIndex configuration class
 *
 * This class defines the structure for sitemap index configuration objects.
 * Implementations specify which models to include, routing configuration,
 * and special handling for news sitemaps.
 */
class IndexSitemap
{
    public array $newsModels = [] {
        get {
            return $this->newsModels;
        }
    }
    public string $prefix = '' {
        get {
            return $this->prefix;
        }
    }
    public array $middleware = [] {
        get {
            return $this->middleware;
        }
    }
    public string $indexSlug = 'sitemap-index' {
        get {
            return $this->indexSlug;
        }
    }
    public string $newsSlug = 'news-sitemap' {
        get {
            return $this->newsSlug;
        }
    }
    protected array $models = [] {
        get {
            return $this->models;
        }
    }

    public function __construct()
    {
        $this->setup();
        $this->validateModels();
    }

    public function setup(): void
    {
    }

    public function validateModels(): bool
    {
        $allModels = array_merge($this->models, $this->newsModels);

        foreach ($allModels as $modelClass) {
            if (!class_exists($modelClass) || !is_subclass_of($modelClass, HasSeo::class)) {
                throw new InvalidArgumentException(
                    "Model $modelClass must implement HasSeo contract"
                );
            }
        }

        return true;
    }

    public static function make(): static
    {
        return new static();
    }

    public function prefix(string $prefix): self
    {
        $this->prefix = $prefix;
        return $this;
    }

    public function middleware(array $middleware): self
    {
        $this->middleware = $middleware;
        return $this;
    }

    public function indexSlug(string $indexSlug): self
    {
        $this->indexSlug = $indexSlug;
        return $this;
    }

    public function newsSlug(string $newsSlug): self
    {
        $this->newsSlug = $newsSlug;
        return $this;
    }

    public function models(array $models): self
    {
        $this->models = $models;
        return $this;
    }

    public function newsModels(array $newsModels): self
    {
        $this->newsModels = $newsModels;
        return $this;
    }

    public function newsLink(): string
    {
        return route($this->newsRouteName());
    }

    public function newsRouteName(): string
    {
        return 'Sitemap'.str($this::class)->pascal()->toString().'News';
    }

    public function indexLink(): string
    {
        return route($this->indexRouteName());
    }

    public function indexRouteName(): string
    {
        return 'Sitemap'.str($this::class)->pascal()->toString().'Index';
    }

    public function isNewsModel(string $modelClass): bool
    {
        return in_array($modelClass, $this->newsModels);
    }

    public function getAllModels(): array
    {
        return array_unique(array_merge($this->models, $this->newsModels));
    }

    public function getIndexContent(): string
    {
        return app(SitemapService::class)->generateIndexSitemap($this);
    }

    public function getNewsContent(): string
    {
        return app(SitemapService::class)->generateNewsSitemap($this);
    }
}
