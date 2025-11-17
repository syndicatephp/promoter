<?php

namespace Syndicate\Promoter\Support;

use Illuminate\Support\HtmlString;

class Hreflang
{
    protected array $links = [];

    public static function make(): self
    {
        return new self;
    }

    public function add(string $hreflang, string $href): self
    {
        $this->links[] = [
            'hreflang' => strtolower(trim($hreflang)),
            'href' => trim($href),
        ];

        return $this;
    }

    public function getLinks(): array
    {
        return $this->links;
    }

    public function render(): HtmlString
    {
        if (empty($this->links)) {
            return new HtmlString('');
        }

        $tags = [];
        foreach ($this->links as $link) {
            $tags[] = '<link rel="alternate" hreflang="'.e($link['hreflang']).'" href="'.e($link['href']).'">';
        }

        return new HtmlString(implode("\n", $tags));
    }
}
