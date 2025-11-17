<?php

namespace Syndicate\Promoter\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Syndicate\Promoter\Models\SeoData;
use Syndicate\Promoter\Services\SeoService;

/**
 * @template TModel of Model
 * @mixin Model
 */
trait HasSeo
{
    public static function bootHasSeo(): void
    {
        static::created(function (Model $model) {
            /** @var HasSeo $model */
            if (!$model->seoData()->exists()) {
                $model->seoData()->create();
            }
        });

        static::deleted(function (Model $model) {
            if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                return;
            }
            /** @var HasSeo $model */
            $model->seoData()->delete();
        });
    }

    public function seoData(): MorphOne
    {
        return $this->morphOne(SeoData::class, 'model');
    }

    public function getSeoAttribute(): SeoService
    {
        return resolve(SeoService::class, ['record' => $this]);
    }
}
