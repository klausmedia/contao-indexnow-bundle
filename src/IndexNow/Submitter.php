<?php

declare(strict_types=1);

namespace KlausMedia\IndexNowBundle\IndexNow;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends the queue to IndexNow. One endpoint is enough: the participating
 * search engines share the reports with each other.
 */
class Submitter
{
    public const ENDPOINT = 'https://api.indexnow.org/indexnow';

    // Limit of the protocol per request
    private const BATCH_SIZE = 10000;

    public function __construct(
        private readonly Queue $queue,
        private readonly HttpClientInterface $httpClient,
        #[Autowire(service: 'monolog.logger.contao.general')]
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param bool $autoOnly only websites with automatic submission (cron job)
     *
     * @return array{sent: int, failed: int, retry: int}
     */
    public function submitQueue(bool $autoOnly = false): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'retry' => 0];

        foreach ($this->queue->getBatches(self::BATCH_SIZE, $autoOnly) as $batch) {
            $count = \count($batch['urls']);
            $status = $this->send($batch['host'], $batch['key'], $batch['scheme'] . '://' . $batch['host'] . '/' . $batch['key'] . '.txt', array_values($batch['urls']));

            // Accepted (202 = key not yet verified, but accepted)
            if (200 === $status || 202 === $status) {
                $this->queue->remove($batch['tstamps']);
                $this->logger->info(\sprintf('IndexNow: %d URL(s) of %s reported (HTTP %d).', $count, $batch['host'], $status));
                $result['sent'] += $count;

                continue;
            }

            // Too many requests, server or network error: try again with the next run
            if (null === $status || 429 === $status || $status >= 500) {
                $this->logger->warning(\sprintf('IndexNow: %d URL(s) of %s not reported (%s), trying again with the next run.', $count, $batch['host'], null === $status ? 'no connection' : 'HTTP ' . $status));
                $result['retry'] += $count;

                continue;
            }

            // 400 bad request, 403 key not valid, 422 URLs do not belong to the host
            $this->queue->remove($batch['tstamps']);
            $this->logger->error(\sprintf('IndexNow: %d URL(s) of %s rejected (HTTP %d). Check the key and the key file %s://%s/%s.txt.', $count, $batch['host'], $status, $batch['scheme'], $batch['host'], $batch['key']));
            $result['failed'] += $count;
        }

        return $result;
    }

    /**
     * @param list<string> $urls
     *
     * @return int|null the HTTP status or null if the request failed
     */
    private function send(string $host, string $key, string $keyLocation, array $urls): int|null
    {
        try {
            $response = $this->httpClient->request('POST', self::ENDPOINT, [
                // As in the protocol; the json option alone sends no charset
                'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                'json' => [
                    'host' => $host,
                    'key' => $key,
                    'keyLocation' => $keyLocation,
                    'urlList' => $urls,
                ],
                'timeout' => 20,
            ]);

            return $response->getStatusCode();
        } catch (ExceptionInterface $exception) {
            $this->logger->warning('IndexNow: ' . $exception->getMessage());

            return null;
        }
    }
}
