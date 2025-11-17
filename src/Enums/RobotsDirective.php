<?php

namespace Syndicate\Promoter\Enums;

/**
 * Represents commonly combined values for the Robots Meta Tag directive.
 */
enum RobotsDirective: string
{
    case INDEX_FOLLOW = 'index,follow';
    case NOINDEX_FOLLOW = 'noindex,follow';
    case INDEX_NOFOLLOW = 'index,nofollow';
    case NOINDEX_NOFOLLOW = 'noindex,nofollow';

    public static function default(): self
    {
        return self::INDEX_FOLLOW;
    }

    public function allowsIndex(): bool
    {
        return match ($this) {
            self::INDEX_FOLLOW, self::INDEX_NOFOLLOW => true,
            self::NOINDEX_FOLLOW, self::NOINDEX_NOFOLLOW => false,
        };
    }

    public function allowsFollow(): bool
    {
        return match ($this) {
            self::INDEX_FOLLOW, self::NOINDEX_FOLLOW => true,
            self::INDEX_NOFOLLOW, self::NOINDEX_NOFOLLOW => false,
        };
    }
}
