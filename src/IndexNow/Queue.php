<?php

declare(strict_types=1);

namespace KlausMedia\IndexNowBundle\IndexNow;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * URLs waiting to be reported. Each URL is stored once together with the
 * website root whose key is used; the cron job sends them in batches.
 */
class Queue
{
    public const TABLE = 'tl_km_indexnow_queue';

    // The key file is only served for published website roots (see KeyFileController)
    private const ACTIVE_ROOTS = "type = 'root' AND published = 1 AND indexnowEnable = 1 AND indexnowKey != ''";

    // Name in tl_km_indexnow_state
    private const CRON_STATE = 'submit_last_run';

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Returns false if no website root with IndexNow is responsible for the host of the URL.
     */
    public function add(string $url): bool
    {
        // Contao generates encoded URLs; anything else (e.g. from a broken sitemap)
        // would also end up unescaped in the list of the backend module
        if (!preg_match('#^https?://#i', $url) || preg_match('/[\s<>"\'`\\\\]/', $url)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        // localhost: URL generated on the command line for a website root without domain
        if (!\is_string($host) || '' === $host || 'localhost' === $host) {
            return false;
        }

        $root = $this->findRoot($host);

        if (null === $root) {
            return false;
        }

        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (tstamp, root, hash, url) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE tstamp = VALUES(tstamp), root = VALUES(root)',
            [time(), $root, sha1($url), $url]
        );

        return true;
    }

    /**
     * Pending URLs of active website roots, grouped by root, scheme and host.
     *
     * @param bool $autoOnly only websites with automatic submission (cron job)
     *
     * @return list<array{root: int, key: string, host: string, scheme: string, urls: array<int, string>, tstamps: array<int, int>}>
     */
    public function getBatches(int $size, bool $autoOnly = false): array
    {
        // URLs of websites that no longer use IndexNow are dropped
        $this->connection->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE root NOT IN (SELECT id FROM tl_page WHERE ' . self::ACTIVE_ROOTS . ')'
        );

        $rows = $this->connection->fetchAllAssociative(
            'SELECT q.id, q.tstamp, q.url, q.root, p.indexnowKey FROM ' . self::TABLE . ' q
             INNER JOIN tl_page p ON p.id = q.root'
             . ($autoOnly ? ' WHERE p.indexnowAutoSubmit = 1' : '') . '
             ORDER BY q.root, q.id'
        );

        $groups = [];

        foreach ($rows as $row) {
            $parts = parse_url((string) $row['url']);
            $host = $parts['host'] ?? '';

            if ('' === $host) {
                $this->remove([(int) $row['id'] => (int) $row['tstamp']]);
                continue;
            }

            // One request per scheme: keyLocation uses the scheme of its URLs, so
            // http and https URLs (e.g. after switching to https) never depend on
            // how a search engine treats a key file of the other scheme
            $scheme = strtolower($parts['scheme'] ?? 'https');
            $groupKey = $row['root'] . '|' . $scheme . '|' . $host;

            $groups[$groupKey] ??= [
                'root' => (int) $row['root'],
                'key' => (string) $row['indexnowKey'],
                'host' => $host,
                'scheme' => $scheme,
                'urls' => [],
                'tstamps' => [],
            ];

            $groups[$groupKey]['urls'][(int) $row['id']] = (string) $row['url'];
            $groups[$groupKey]['tstamps'][(int) $row['id']] = (int) $row['tstamp'];
        }

        $batches = [];

        foreach ($groups as $group) {
            foreach (array_chunk($group['urls'], $size, true) as $urls) {
                $batches[] = ['urls' => $urls, 'tstamps' => array_intersect_key($group['tstamps'], $urls)] + $group;
            }
        }

        return $batches;
    }

    /**
     * Entries queued again in the meantime (new tstamp) are kept.
     *
     * @param array<int, int> $tstamps ID => tstamp at the time of reading
     */
    public function remove(array $tstamps): void
    {
        $groups = [];

        foreach ($tstamps as $id => $tstamp) {
            $groups[$tstamp][] = $id;
        }

        foreach ($groups as $tstamp => $ids) {
            $this->connection->executeStatement(
                'DELETE FROM ' . self::TABLE . ' WHERE tstamp = ? AND id IN (?)',
                [$tstamp, $ids],
                [ParameterType::INTEGER, ArrayParameterType::INTEGER]
            );
        }
    }

    public function count(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ' . self::TABLE);
    }

    /**
     * Website roots with IndexNow, optionally only those responsible for a host.
     *
     * @return list<array{id: int, dns: string, useSSL: bool}>
     */
    public function getRoots(string|null $host = null): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, dns, useSSL FROM tl_page WHERE ' . self::ACTIVE_ROOTS . ' ORDER BY sorting'
        );

        $roots = array_map(
            static fn (array $row): array => ['id' => (int) $row['id'], 'dns' => (string) $row['dns'], 'useSSL' => (bool) $row['useSSL']],
            $rows
        );

        if (null === $host) {
            return $roots;
        }

        return array_values(array_filter($roots, static fn (array $root): bool => \in_array($root['dns'], [$host, ''], true)));
    }

    /**
     * Called by the cron job, so the backend can tell whether it runs at all.
     */
    public function markCronRun(): void
    {
        $this->connection->executeStatement(
            'INSERT INTO tl_km_indexnow_state (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [self::CRON_STATE, time()]
        );
    }

    /**
     * @return int|null Unix time of the last run of the cron job, null if it never ran
     */
    public function getLastCronRun(): int|null
    {
        $value = $this->connection->fetchOne('SELECT value FROM tl_km_indexnow_state WHERE name = ?', [self::CRON_STATE]);

        return false === $value ? null : (int) $value;
    }

    public function hasAutoRoots(): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT id FROM tl_page WHERE ' . self::ACTIVE_ROOTS . ' AND indexnowAutoSubmit = 1 LIMIT 1'
        );
    }

    /**
     * Titles of the website roots with IndexNow whose URLs are only sent by hand.
     *
     * @return list<string>
     */
    public function getManualRootTitles(): array
    {
        return array_map('strval', $this->connection->fetchFirstColumn(
            'SELECT title FROM tl_page WHERE ' . self::ACTIVE_ROOTS . ' AND indexnowAutoSubmit != 1 ORDER BY sorting'
        ));
    }

    /**
     * Website roots with IndexNow that are responsible for a host. As in the
     * routing of Contao, roots with this domain take precedence over roots
     * without domain: a fallback root with IndexNow must not report the URLs of
     * a website whose own root has IndexNow switched off or is not published.
     *
     * @return list<array{id: int, key: string}>
     */
    public function getRootsForHost(string $host): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, dns, published, indexnowEnable, indexnowKey FROM tl_page
             WHERE type = 'root' AND (dns = ? OR dns = '')
             ORDER BY dns DESC, sorting",
            [$host]
        );

        $roots = [];

        foreach ($rows as $row) {
            if ('' === (string) $row['dns'] && '' !== (string) $rows[0]['dns']) {
                break;
            }

            if ($row['published'] && $row['indexnowEnable'] && '' !== (string) $row['indexnowKey']) {
                $roots[] = ['id' => (int) $row['id'], 'key' => (string) $row['indexnowKey']];
            }
        }

        return $roots;
    }

    /**
     * The website root whose key is used for the host.
     */
    private function findRoot(string $host): int|null
    {
        return $this->getRootsForHost($host)[0]['id'] ?? null;
    }
}
