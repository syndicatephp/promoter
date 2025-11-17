<?php

namespace Syndicate\Promoter\Services;

use Astrotomic\OpenGraph\TwitterType;
use Astrotomic\OpenGraph\Type;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use RuntimeException;
use Spatie\SchemaOrg\Graph;
use Syndicate\Promoter\Contracts\SeoConfig;
use Syndicate\Promoter\Support\Hreflang;

class SeoService implements Htmlable
{
    protected SeoConfig $seoConfig;

    public function __construct(protected Model $record)
    {
        $this->seoConfig = $this->resolveSeoConfig();
    }

    protected function resolveSeoConfig(): SeoConfig
    {
        $model = $this->record;
        $modelClass = get_class($model);

        if (property_exists($modelClass, 'seoConfigClass') && $modelClass::$seoConfigClass) {
            $fallbackClass = $modelClass::$seoConfigClass;

            if (is_string($fallbackClass) && class_exists($fallbackClass)) {
                return app($fallbackClass);
            }
        }

        $configClass = $this->guessConfigFromConvention($modelClass);

        if ($configClass && class_exists($configClass)) {
            return app($configClass);
        }

        throw new RuntimeException(
            sprintf('Unable to resolve SeoConfig for model [%s]', $modelClass)
        );
    }

    protected function guessConfigFromConvention(string $modelClass): ?string
    {
        $reflection = new ReflectionClass($modelClass);
        $shortName = $reflection->getShortName();
        $modelNamespace = $reflection->getNamespaceName();
        $namespaceParts = explode('\\', $modelNamespace);
        $modelsIndex = array_search('Models', $namespaceParts, true);

        if ($modelsIndex === false) {
            return null;
        }

        $rootParts = array_slice($namespaceParts, 0, $modelsIndex);

        $seoNamespaceParts = array_merge($rootParts, ['Syndicate', 'Promoter', 'Seo']);
        $seoNamespace = implode('\\', $seoNamespaceParts);

        return $seoNamespace.'\\'.$shortName.'SeoConfig';
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
        return view('syndicate-promoter::seo', [
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
        return $this->sanitizeString($this->seoConfig->title($this->record, $this));
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
        return $this->sanitizeString($this->seoConfig->description($this->record, $this));
    }

    public function getCanonicalUrl(): ?string
    {
        return $this->sanitizeString($this->seoConfig->canonicalUrl($this->record, $this));
    }

    public function getRobots(): ?string
    {
        return $this->sanitizeString($this->seoConfig->robots($this->record, $this));
    }

    public function getTwitter(): ?TwitterType
    {
        return $this->seoConfig->twitter($this->record, $this);
    }

    public function getOpenGraph(): ?Type
    {
        return $this->seoConfig->openGraph($this->record, $this);
    }

    public function getSchema(): Graph|null
    {
        return $this->seoConfig->schema($this->record, $this);
    }

    public function getHreflang(): ?Hreflang
    {
        return $this->seoConfig->hreflang($this->record, $this);
    }

    public function getKeywords(): ?string
    {
        return $this->sanitizeString($this->sanitizeString($this->seoConfig->keywords($this->record, $this)));
    }
}
