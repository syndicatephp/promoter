<?php

namespace Syndicate\Promoter\Contracts;

use Astrotomic\OpenGraph\TwitterType;
use Astrotomic\OpenGraph\Type;
use Illuminate\Database\Eloquent\Model;
use Spatie\SchemaOrg\Graph;
use Syndicate\Promoter\Services\SeoService;
use Syndicate\Promoter\Support\Hreflang;

interface SeoConfig
{
    public function title(Model $record, SeoService $service): ?string;

    public function description(Model $record, SeoService $service): ?string;

    public function keywords(Model $record, SeoService $service): ?string;

    public function robots(Model $record, SeoService $service): ?string;

    public function canonicalUrl(Model $record, SeoService $service): ?string;

    public function hreflang(Model $record, SeoService $service): ?Hreflang;

    public function openGraph(Model $record, SeoService $service): ?Type;

    public function twitter(Model $record, SeoService $service): ?TwitterType;

    public function schema(Model $record, SeoService $service): Graph|null;
}
