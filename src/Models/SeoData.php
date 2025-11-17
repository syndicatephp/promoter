<?php

namespace Syndicate\Promoter\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SeoData extends Model
{
    protected $table = 'seo_data';

    protected $fillable = [
        'title',
        'description',
        'image_alt',
        'keywords',
        'keyword',
        'generated_keyword',
        'keyword_score',
        'canonical_url',
        'robots',
    ];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
