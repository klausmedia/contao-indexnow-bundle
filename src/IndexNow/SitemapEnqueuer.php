<?php

declare(strict_types=1);

namespace KlausMedia\IndexNowBundle\IndexNow;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Queues all URLs of /sitemap.xml of each website with IndexNow, e.g. once
 * after setting it up. Used by the console command and the backend module.
 */
class SitemapEnqueuer
{
    public const ERROR_NO_DOMAIN = 'no_domain';
    public const ERROR_UNREACHABLE = 'unreachable';
    public const ERROR_INVALID = 'invalid';

    public function __construct(
        private readonly Queue $queue,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @param string|null $host           only the website roots responsible for this domain
     * @param string|null $fallbackDomain domain for website roots without domain
     *
     * @return list<array{root: int, sitemap: string|null, added: int, errors: list<array{code: string, url: string, message: string}>}>
     */
    public function enqueueAll(string|null $host = null, string|null $fallbackDomain = null): array
    {
        $results = [];
        $done = [];

        foreach ($this->queue->getRoots($host) as $root) {
            $domain = '' !== $root['dns'] ? $root['dns'] : $fallbackDomain;

            if (!$domain) {
                $results[] = ['root' => $root['id'], 'sitemap' => null, 'added' => 0, 'errors' => [['code' => self::ERROR_NO_DOMAIN, 'url' => '', 'message' => '']]];
                continue;
            }

            $sitemap = ($root['useSSL'] ? 'https' : 'http') . '://' . $domain . '/sitemap.xml';

            // Website roots of one domain (e.g. one per language) share the sitemap
            if (isset($done[$sitemap])) {
                continue;
            }

            $done[$sitemap] = true;
            $errors = [];
            $urls = $this->readSitemap($sitemap, $errors);

            $results[] = [
                'root' => $root['id'],
                'sitemap' => $sitemap,
                'added' => \count(array_filter(array_map($this->queue->add(...), $urls))),
                'errors' => $errors,
            ];
        }

        return $results;
    }

    /**
     * @param list<array{code: string, url: string, message: string}> $errors
     *
     * @return list<string>
     */
    private function readSitemap(string $url, array &$errors, int $depth = 0): array
    {
        try {
            $xml = $this->httpClient->request('GET', $url, ['timeout' => 30])->getContent();
        } catch (ExceptionInterface $exception) {
            $errors[] = ['code' => self::ERROR_UNREACHABLE, 'url' => $url, 'message' => $exception->getMessage()];

            return [];
        }

        $document = @simplexml_load_string($xml, options: LIBXML_NONET);

        if (false === $document) {
            $errors[] = ['code' => self::ERROR_INVALID, 'url' => $url, 'message' => ''];

            return [];
        }

        $locations = [];

        foreach ($document->children() as $entry) {
            $location = trim((string) $entry->loc);

            if ('' !== $location) {
                $locations[] = $location;
            }
        }

        // Sitemap index: read the sitemaps it lists
        if ('sitemapindex' === $document->getName()) {
            if ($depth >= 1) {
                return [];
            }

            $urls = [];

            foreach ($locations as $sitemap) {
                array_push($urls, ...$this->readSitemap($sitemap, $errors, $depth + 1));
            }

            return $urls;
        }

        return $locations;
    }
}
