<?php

namespace Syndicate\Promoter\Services;

use Astrotomic\OpenGraph\TwitterType;
use Astrotomic\OpenGraph\Type;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Spatie\SchemaOrg\Graph;
use Syndicate\Promoter\Contracts\Seo;
use Syndicate\Promoter\Support\Hreflang;

class SeoService implements Htmlable
{
    protected static array $guessCache = [];
    protected Seo $seo;

    public function __construct(protected Model $record)
    {
        $this->seo = $this->resolveSeoConfig();
    }

    protected function resolveSeoConfig(): Seo
    {
        $model = $this->record;
        $modelClass = get_class($model);

        // 1. Check for explicit property on Model
        if (property_exists($modelClass, 'seoDefinition')) {
            return app($modelClass::$seoDefinition);
        }

        // 2. Check Memory Cache (Memoization)
        if (isset(static::$guessCache[$modelClass])) {
            return app(static::$guessCache[$modelClass]);
        }

        // 3. Run the Guesser
        $configClass = $this->guessClassName($modelClass);

        if (class_exists($configClass)) {
            static::$guessCache[$modelClass] = $configClass;
            return app($configClass);
        }

        throw new RuntimeException(
            sprintf('Unable to resolve Seo for model [%s]', $modelClass)
        );
    }

    protected function guessClassName(string $modelClass): string
    {
        return 'App\\Syndicate\\Promoter\\Seo\\' . class_basename($modelClass) . 'Seo';
    }

    public static function make(Model $record): self
    {
        return app(static::class, ['record' => $record]);
    }

    public function toHtml(): string
    {
        return $this->render()->render();
    }

    public function render(): View
    {
        return view('promoter::seo', [
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'canonicalUrl' => $this->getCanonicalUrl(),
            'robots' => $this->getRobots(),
            'twitter' => $this->getTwitter(),
            'openGraph' => $this->getOpenGraph(),
            'schema' => $this->getSchema(),
            'hreflang' => $this->getHreflang(),
            'keywords' => $this->getKeywords(),
        ]);
    }

    public function getTitle(): ?string
    {
        return $this->sanitizeString($this->seo->title($this->record, $this));
    }

    protected function sanitizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmedValue = trim($value);

        return $trimmedValue === '' ? null : $trimmedValue;
    }

    public function getDescription(): ?string
    {
        return $this->sanitizeString($this->seo->description($this->record, $this));
    }

    public function getCanonicalUrl(): ?string
    {
        return $this->sanitizeString($this->seo->canonicalUrl($this->record, $this));
    }

    public function getRobots(): ?string
    {
        return $this->sanitizeString($this->seo->robots($this->record, $this));
    }

    public function getTwitter(): ?TwitterType
    {
        return $this->seo->twitter($this->record, $this);
    }

    public function getOpenGraph(): ?Type
    {
        return $this->seo->openGraph($this->record, $this);
    }

    public function getSchema(): Graph|null
    {
        return $this->seo->schema($this->record, $this);
    }

    public function getHreflang(): ?Hreflang
    {
        return $this->seo->hreflang($this->record, $this);
    }

    public function getKeywords(): ?string
    {
        return $this->sanitizeString($this->sanitizeString($this->seo->keywords($this->record, $this)));
    }
}
