<?php

namespace Syndicate\Promoter\Sitemaps;

use Carbon\Carbon;
use Closure;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Syndicate\Lawyer\Contracts\HasUrl;
use Syndicate\Promoter\DTOs\NewsMetadataDto;
use Syndicate\Promoter\Services\SitemapService;

class ModelSitemap
{
    public string $sitemapSlug {
        get {
            return $this->sitemapSlug;
        }
    }
    protected ?Closure $url = null;
    protected ?Closure $priority = null;
    protected ?Closure $changeFrequency = null;
    protected ?Closure $lastModified = null;
    protected ?Closure $images = null;
    protected ?Closure $translations = null;
    protected ?Closure $newsMetadata = null;
    protected ?Closure $shouldBeInSitemap = null;
    protected ?Closure $scope = null;
    protected ?Closure $scopeLastModified = null;

    /**
     * @param  class-string<Model>  $model
     */
    public function __construct(protected string $model)
    {
        $this->sitemapSlug = str(class_basename($model))->slug()->toString().'-sitemap';
    }

    public static function make(string $model): self
    {
        return app(static::class, ['model' => $model]);
    }

    public function link(): string
    {
        return route($this->routeName());
    }

    public function routeName(): string
    {
        return 'Sitemap'.str($this->model)->pascal()->toString();
    }

    public function url(Closure $closure): self
    {
        $this->url = $closure;
        return $this;
    }

    public function priority(Closure $closure): self
    {
        $this->priority = $closure;
        return $this;
    }

    public function changeFrequency(Closure $closure): self
    {
        $this->changeFrequency = $closure;
        return $this;
    }

    public function lastModified(Closure $closure): self
    {
        $this->lastModified = $closure;
        return $this;
    }

    public function images(Closure $closure): self
    {
        $this->images = $closure;
        return $this;
    }

    public function translations(Closure $closure): self
    {
        $this->translations = $closure;
        return $this;
    }

    public function newsMetadata(Closure $closure): self
    {
        $this->newsMetadata = $closure;
        return $this;
    }

    public function filter(Closure $closure): self
    {
        $this->shouldBeInSitemap = $closure;
        return $this;
    }

    public function tapQuery(Closure $closure): self
    {
        $this->scope = $closure;
        return $this;
    }

    public function sitemapSlug(string $slug): self
    {
        $this->sitemapSlug = $slug;
        return $this;
    }

    public function getUrl(Model $record): ?string
    {
        $evaluated = $this->evaluate($this->url, $record);

        if (is_string($evaluated)) {
            return $evaluated;
        }

        if ($record instanceof HasUrl) {
            return $record->getUrl();
        }

        return null;
    }

    protected function evaluate(?Closure $closure, Model $record)
    {
        if (!$closure instanceof Closure) {
            return $closure;
        }

        return $closure($record);
    }

    public function getPriority(Model $record): ?float
    {
        $priority = $this->evaluate($this->priority, $record);
        return $priority !== null ? (float) $priority : null;
    }

    public function getChangeFrequency(Model $record): ?string
    {
        return $this->evaluate($this->changeFrequency, $record);
    }

    public function getLastModified(Model $record): ?Carbon
    {
        $lastMod = $this->evaluate($this->lastModified, $record);
        return $lastMod;
    }

    public function getSitemapLastModified(): Carbon
    {
        return ($this->scopeLastModified)();
    }

    public function getImages(Model $record): Collection
    {
        $images = $this->evaluate($this->images, $record);
        if ($images instanceof Collection) {
            return $images;
        }
        if (is_array($images)) {
            return collect($images);
        }
        if ($images !== null) {
            return collect([$images]);
        }
        return collect();
    }

    public function getTranslations(Model $record): Collection
    {
        $translations = $this->evaluate($this->translations, $record);
        if ($translations instanceof Collection) {
            return $translations;
        }
        if (is_array($translations)) {
            return collect($translations);
        }
        return collect();
    }

    public function getNewsMetadata(Model $record): NewsMetadataDto|array
    {
        $metadata = $this->evaluate($this->newsMetadata, $record);

        // If it's already a DTO, return it
        if ($metadata instanceof NewsMetadataDto) {
            return $metadata;
        }

        // If it's an array, convert to DTO for type safety
        if (is_array($metadata)) {
            try {
                return NewsMetadataDto::fromArray($metadata);
            } catch (Exception $e) {
                // If conversion fails, return array for backward compatibility
                return $metadata;
            }
        }

        return [];
    }

    public function getShouldBeInSitemap(Model $record): bool
    {
        $should = $this->evaluate($this->shouldBeInSitemap, $record);
        return $should !== null ? (bool) $should : true;
    }

    public function getBaseQuery(): Builder
    {
        $query = $this->model::query();

        if ($this->scope instanceof Closure) {
            ($this->scope)($query);
        }

        return $query;
    }

    public function sitemapLastModified(Closure $closure): self
    {
        $this->scopeLastModified = $closure;
        return $this;
    }

    public function hasImages(): bool
    {
        return $this->images !== null;
    }

    public function hasTranslations(): bool
    {
        return $this->translations !== null;
    }

    public function isNewsItem(): bool
    {
        return $this->newsMetadata !== null;
    }

    public function getContent(): string
    {
        return resolve(SitemapService::class)->generateSitemapContent($this);
    }
}
