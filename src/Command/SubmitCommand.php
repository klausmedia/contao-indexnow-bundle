<?php

declare(strict_types=1);

namespace KlausMedia\IndexNowBundle\Command;

use KlausMedia\IndexNowBundle\IndexNow\Queue;
use KlausMedia\IndexNowBundle\IndexNow\SitemapEnqueuer;
use KlausMedia\IndexNowBundle\IndexNow\Submitter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reports the queue right away, also of websites without automatic
 * submission. With --all, all URLs of the sitemap of each website with
 * IndexNow are queued first, e.g. once after setting it up.
 */
#[AsCommand(name: 'contao:indexnow:submit', description: 'Reports the queued URLs to IndexNow (with --all, all URLs of the sitemap).')]
class SubmitCommand extends Command
{
    public function __construct(
        private readonly Queue $queue,
        private readonly Submitter $submitter,
        private readonly SitemapEnqueuer $sitemapEnqueuer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('all', null, InputOption::VALUE_NONE, 'Report all URLs in /sitemap.xml of the websites')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Only this domain (required for a website root without domain)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $host = $input->getOption('host');

        if ($input->getOption('all')) {
            $results = $this->sitemapEnqueuer->enqueueAll($host, $host);

            if ([] === $results) {
                $io->error('No website root with IndexNow found' . ($host ? ' for ' . $host : '') . '.');

                return Command::FAILURE;
            }

            foreach ($results as $result) {
                foreach ($result['errors'] as $error) {
                    $io->warning(match ($error['code']) {
                        SitemapEnqueuer::ERROR_NO_DOMAIN => \sprintf('Website root ID %d has no domain: run the command with --host=<domain>.', $result['root']),
                        SitemapEnqueuer::ERROR_INVALID => $error['url'] . ': not a valid sitemap.',
                        default => $error['url'] . ': ' . $error['message'],
                    });
                }

                if (null !== $result['sitemap']) {
                    $io->text(\sprintf('%s: %d URL(s) added to the queue.', $result['sitemap'], $result['added']));
                }
            }
        }

        if (0 === $this->queue->count()) {
            $io->success('No URLs to report.');

            return Command::SUCCESS;
        }

        $result = $this->submitter->submitQueue();

        $io->definitionList(
            ['Reported' => $result['sent']],
            ['Rejected' => $result['failed']],
            ['Retry later' => $result['retry']],
            ['On hold (maintenance mode)' => $this->queue->countOnHold()],
        );

        if ($result['failed'] > 0) {
            $io->error('IndexNow has rejected URLs, see the Contao system log for details.');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
