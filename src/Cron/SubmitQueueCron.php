<?php

declare(strict_types=1);

namespace KlausMedia\IndexNowBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use KlausMedia\IndexNowBundle\IndexNow\Queue;
use KlausMedia\IndexNowBundle\IndexNow\Submitter;

/**
 * Reports the queued URLs every five minutes, so several changes in a row
 * end up in one request. Websites with "Automatisch senden" switched off are
 * skipped: their URLs stay in the queue until they are sent in the backend.
 */
#[AsCronJob('*/5 * * * *')]
class SubmitQueueCron
{
    public function __construct(
        private readonly Submitter $submitter,
        private readonly Queue $queue,
    ) {
    }

    public function __invoke(): void
    {
        $this->queue->markCronRun();
        $this->submitter->submitQueue(autoOnly: true);
    }
}
