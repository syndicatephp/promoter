<?php

namespace Syndicate\Promoter\Services;

use Illuminate\Database\Eloquent\Model;
use Syndicate\Promoter\DTOs\NewsMetadataDto;
use Syndicate\Promoter\Sitemaps\IndexSitemap;
use Syndicate\Promoter\Sitemaps\ModelSitemap;

class SitemapService
{
    public function generateSitemapContent(ModelSitemap $sitemap): string
    {
        $xml = $this->createSitemap('urlset');

        $usedNamespaces = [
            'image' => false,
            'news' => false,
            'xhtml' => false
        ];

        $sitemap->getBaseQuery()
            ->chunk(20, function ($records) use (&$xml, $sitemap, &$usedNamespaces) {
                foreach ($records as $record) {
                    $this->addSitemapItem($xml, $record, $sitemap, $usedNamespaces);
                }
            });

        $this->addRequiredNamespaces($xml, $usedNamespaces);

        return $xml->saveXML();
    }

    /**
     * Create base sitemap structure
     */
    private function createSitemap(
        string $sitemapType,
        bool   $image = false,
        bool   $news = false,
        bool   $href = false
    ): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $urlset = $dom->createElement($sitemapType);
        $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        if ($image) {
            $urlset->setAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');
        }

        if ($news) {
            $urlset->setAttribute('xmlns:news', 'http://www.google.com/schemas/sitemap-news/0.9');
        }

        if ($href) {
            $urlset->setAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');
        }

        $dom->appendChild($urlset);
        return $dom;
    }

    /**
     * Add sitemap item from HasSeo model using per-model Sitemap
     */
    private function addSitemapItem(
        DOMDocument  $xml,
        Model        $record,
        ModelSitemap $sitemap,
        array        &$usedNamespaces = [],
        bool         $isNewsSitemap = false
    ): void
    {
        // Check if the record should be in sitemap using sitemap
        if (!$sitemap->getShouldBeInSitemap($record)) {
            return;
        }

        $urlElement = $xml->createElement('url');

        // Add location using sitemap
        $url = $sitemap->getUrl($record);
        if ($url) {
            $locElement = $xml->createElement('loc', htmlspecialchars($url, ENT_XML1, 'UTF-8'));
            $urlElement->appendChild($locElement);
        }

        // Add last modified using sitemap
        $lastMod = $sitemap->getLastModified($record);
        if ($lastMod) {
            $lastmodElement = $xml->createElement('lastmod', $lastMod->toAtomString());
            $urlElement->appendChild($lastmodElement);
        }

        // Add priority using sitemap
        $priority = $sitemap->getPriority($record);
        if ($priority !== null) {
            $priorityElement = $xml->createElement('priority', (string)$priority);
            $urlElement->appendChild($priorityElement);
        }

        // Add change frequency using sitemap
        $changeFreq = $sitemap->getChangeFrequency($record);
        if ($changeFreq) {
            $changeFreqElement = $xml->createElement('changefreq', $changeFreq);
            $urlElement->appendChild($changeFreqElement);
        }

        // Add translations if enabled
        if ($sitemap->hasTranslations()) {
            $this->addTranslations($xml, $urlElement, $sitemap, $record, $usedNamespaces);
        }

        // Add images if enabled
        if ($sitemap->hasImages()) {
            $this->addImages($xml, $urlElement, $sitemap, $record, $usedNamespaces);
        }

        // Add news metadata if enabled and this is a news sitemap
        if ($isNewsSitemap && $sitemap->isNewsItem()) {
            $this->addNewsMetadata($xml, $urlElement, $sitemap, $record, $usedNamespaces);
        }

        $xml->documentElement->appendChild($urlElement);
    }

    private function addTranslations(
        DOMDocument  $xml,
        DOMElement   $urlElement,
        ModelSitemap $sitemap,
                     $record,
        array        &$usedNamespaces = []
    ): void
    {
        $translations = $sitemap->getTranslations($record);

        if ($translations->isNotEmpty()) {
            // Mark xhtml namespace as used
            $usedNamespaces['xhtml'] = true;

            foreach ($translations as $translation) {
                $hrefElement = $xml->createElement('xhtml:link');
                $hrefElement->setAttribute('rel', 'alternate');
                $hrefElement->setAttribute('hreflang', $translation->language->getSlug());
                $hrefElement->setAttribute('href', $translation->link());
                $urlElement->appendChild($hrefElement);
            }
        }
    }

    private function addImages(
        DOMDocument  $xml,
        DOMElement   $urlElement,
        ModelSitemap $sitemap,
                     $record,
        array        &$usedNamespaces = []
    ): void
    {
        $images = $sitemap->getImages($record);

        if ($images->isNotEmpty()) {
            // Mark the image namespace as used
            $usedNamespaces['image'] = true;

            foreach ($images as $imageUrl) {
                $imageElement = $xml->createElement('image:image');
                $imageLocElement = $xml->createElement('image:loc', htmlspecialchars($imageUrl, ENT_XML1, 'UTF-8'));

                $imageElement->appendChild($imageLocElement);
                $urlElement->appendChild($imageElement);
            }
        }
    }

    /**
     * Add news metadata from per-model Sitemap
     */
    private function addNewsMetadata(
        DOMDocument  $xml,
        DOMElement   $urlElement,
        ModelSitemap $sitemap,
                     $record,
        array        &$usedNamespaces = []
    ): void
    {
        $newsMetadata = $sitemap->getNewsMetadata($record);

        // Mark news namespace as used
        $usedNamespaces['news'] = true;

        $newsElement = $xml->createElement('news:news');

        // Publication info
        $publicationElement = $xml->createElement('news:publication');

        // Handle both DTO and array formats
        if ($newsMetadata instanceof NewsMetadataDto) {
            $nameElement = $xml->createElement('news:name', $newsMetadata->publicationName);
            $publicationElement->appendChild($nameElement);

            $languageElement = $xml->createElement('news:language', $newsMetadata->publicationLanguage);
            $publicationElement->appendChild($languageElement);

            $newsElement->appendChild($publicationElement);

            // Publication date
            $publicationDate = $xml->createElement('news:publication_date',
                htmlspecialchars($newsMetadata->getPublicationDateString(), ENT_XML1, 'UTF-8'));
            $newsElement->appendChild($publicationDate);

            // Title
            $title = $xml->createElement('news:title',
                htmlspecialchars($newsMetadata->title, ENT_XML1, 'UTF-8'));
            $newsElement->appendChild($title);
        } else {
            // Backward compatibility for array format
            $nameElement = $xml->createElement('news:name', $newsMetadata['publication_name'] ?? config('app.name'));
            $publicationElement->appendChild($nameElement);

            $languageElement = $xml->createElement('news:language',
                $newsMetadata['publication_language'] ?? app()->getLocale());
            $publicationElement->appendChild($languageElement);

            $newsElement->appendChild($publicationElement);

            // Publication date
            $publicationDate = $xml->createElement('news:publication_date',
                htmlspecialchars($newsMetadata['publication_date'], ENT_XML1, 'UTF-8'));
            $newsElement->appendChild($publicationDate);

            // Title
            $title = $xml->createElement('news:title',
                htmlspecialchars($newsMetadata['title'], ENT_XML1, 'UTF-8'));
            $newsElement->appendChild($title);
        }

        $urlElement->appendChild($newsElement);
    }

    /**
     * Add required namespaces to sitemap based on actual usage
     */
    private function addRequiredNamespaces(DOMDocument $xml, array $usedNamespaces): void
    {
        $rootElement = $xml->documentElement;

        if ($usedNamespaces['image']) {
            $rootElement->setAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');
        }

        if ($usedNamespaces['news']) {
            $rootElement->setAttribute('xmlns:news', 'http://www.google.com/schemas/sitemap-news/0.9');
        }

        if ($usedNamespaces['xhtml']) {
            $rootElement->setAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');
        }
    }

    public function generateIndexSitemap(IndexSitemap $sitemap): string
    {
        $models = $sitemap->getAllModels();
        $xml = $this->createSitemap('sitemapindex');
        $newsLastMod = now();

        foreach ($models as $modelClass) {
            $lastMod = $modelClass::sitemap()->getSitemapLastModified();
            $link = $modelClass::sitemap()->link();

            if ($sitemap->isNewsModel($modelClass)) {
                $newsLastMod = $lastMod;
            }

            $this->addSitemap(
                $xml,
                $link,
                $lastMod->toAtomString()
            );
        }

        if (!empty($sitemap->newsModels)) {
            $link = $sitemap->newsLink();
            $this->addSitemap(
                $xml,
                $link,
                $newsLastMod->toAtomString()
            );
        }

        return $xml->saveXML();
    }

    /**
     * Add sitemap entry to sitemap index
     */
    private function addSitemap(DOMDocument $xml, string $loc, string $lastmod): void
    {
        $sitemapElement = $xml->createElement('sitemap');

        $locElement = $xml->createElement('loc', htmlspecialchars($loc, ENT_XML1, 'UTF-8'));
        $sitemapElement->appendChild($locElement);

        $lastmodElement = $xml->createElement('lastmod', $lastmod);
        $sitemapElement->appendChild($lastmodElement);

        $xml->documentElement->appendChild($sitemapElement);
    }

    public function generateNewsSitemap(IndexSitemap $sitemapIndex): string
    {
        $newsModels = $sitemapIndex->newsModels;
        $xml = $this->createSitemap('urlset');

        $usedNamespaces = [
            'image' => false,
            'news' => false,
            'xhtml' => false
        ];

        foreach ($newsModels as $modelClass) {
            if (!class_exists($modelClass) || !is_subclass_of($modelClass, HasSeo::class)) {
                continue;
            }

            $sitemap = $modelClass::sitemap();
            $baseQuery = $sitemap->getBaseQuery();

            if (new $modelClass() instanceof HasRevisedAt) {
                $baseQuery->whereDate('revised_at', '>', now()->subDays(2));
            } else {
                $baseQuery->whereDate('published_at', '>', now()->subDays(2));
            }

            $baseQuery
                ->chunk(20, function ($records) use (&$xml, $sitemap, &$usedNamespaces) {
                    foreach ($records as $record) {
                        $this->addSitemapItem($xml, $record, $sitemap, $usedNamespaces, true);
                    }
                });
        }

        $this->addRequiredNamespaces($xml, $usedNamespaces);

        return $xml->saveXML();
    }
}
