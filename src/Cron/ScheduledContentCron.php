<?php

declare(strict_types=1);

namespace KlausMedia\IndexNowBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Doctrine\DBAL\Connection;
use KlausMedia\IndexNowBundle\IndexNow\ContentUrlCollector;
use KlausMedia\IndexNowBundle\IndexNow\Queue;

/**
 * Queues the URLs of records whose start or stop date ("show from" / "show
 * until") was reached since the last run. Saving such a record does not
 * report it, because it is not visible yet or still visible at that time.
 * SubmitQueueCron sends the queue.
 */
#[AsCronJob('*/5 * * * *')]
class ScheduledContentCron
{
    private const STATE_TABLE = 'tl_km_indexnow_state';

    private const STATE_NAME = 'scheduled_last_check';

    // Tables with start/stop fields (tl_faq has none)
    private const TABLES = ['tl_page', 'tl_news', 'tl_calendar_events', 'tl_article', 'tl_content'];

    // First run: look back one hour instead of reporting the whole history
    private const FIRST_RUN_WINDOW = 3600;

    public function __construct(
        private readonly Connection $connection,
        private readonly ContentUrlCollector $urlCollector,
        private readonly Queue $queue,
    ) {
    }

    public function __invoke(): void
    {
        $now = time();
        $lastCheck = $this->connection->fetchOne(
            'SELECT value FROM ' . self::STATE_TABLE . ' WHERE name = ?',
            [self::STATE_NAME]
        );

        $lastCheck = false !== $lastCheck ? (int) $lastCheck : $now - self::FIRST_RUN_WINDOW;

        if ($lastCheck < $now) {
            $existing = $this->connection->createSchemaManager()->listTableNames();

            foreach (self::TABLES as $table) {
                if (\in_array($table, $existing, true)) {
                    $this->queueTable($table, $lastCheck, $now);
                }
            }
        }

        // Only stored after a complete run, so nothing is skipped after an error
        $this->connection->executeStatement(
            'INSERT INTO ' . self::STATE_TABLE . ' (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [self::STATE_NAME, $now]
        );
    }

    private function queueTable(string $table, int $from, int $to): void
    {
        // Drafts were never visible
        $visible = 'tl_content' === $table ? 'invisible = 0' : 'published = 1';

        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, start, stop FROM $table
             WHERE $visible AND ((start != '' AND CAST(start AS UNSIGNED) > :from AND CAST(start AS UNSIGNED) <= :to)
                OR (stop != '' AND CAST(stop AS UNSIGNED) > :from AND CAST(stop AS UNSIGNED) <= :to))",
            ['from' => $from, 'to' => $to]
        );

        foreach ($rows as $row) {
            $stop = (string) $row['stop'];
            $stopReached = '' !== $stop && (int) $stop > $from && (int) $stop <= $to;

            // An expired page, news or event is reported so search engines notice that it is gone.
            // For articles and elements getUrlFor() always reports the published owner.
            $url = $this->urlCollector->getUrlFor($table, (int) $row['id'], !$stopReached);

            if (null !== $url) {
                $this->queue->add($url);
            }
        }
    }
}
