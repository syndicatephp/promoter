<?php

namespace Syndicate\Promoter\Traits;

use Illuminate\Database\Eloquent\Model;
use Syndicate\Promoter\Sitemaps\ModelSitemap;

/**
 * @template TModel of Model
 * @mixin Model
 */
trait HasSitemap
{
    public static function sitemap(): ModelSitemap
    {
        return ModelSitemap::make(static::class);
    }
}
