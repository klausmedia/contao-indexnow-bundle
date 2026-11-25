<?php

declare(strict_types=1);

namespace KlausMedia\IndexNowBundle\Callback;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use KlausMedia\IndexNowBundle\IndexNow\ContentUrlCollector;
use KlausMedia\IndexNowBundle\IndexNow\Queue;

/**
 * Puts the URL of a page, news, event or FAQ into the queue when it is saved
 * while published, published or unpublished (also with the toggle icon),
 * deleted, or gets a new alias. Changes to articles and content elements
 * report the page, news or event they belong to. The cron job reports the
 * queue; ScheduledContentCron covers start and stop dates.
 */
class ContentChangeCallback
{
    /**
     * Pages whose alias changes in this request (save callback before onsubmit).
     *
     * @var array<int, true>
     */
    private array $aliasChangedPages = [];

    public function __construct(
        private readonly ContentUrlCollector $urlCollector,
        private readonly Queue $queue,
        private readonly Connection $connection,
    ) {
    }

    #[AsCallback(table: 'tl_page', target: 'config.onsubmit')]
    #[AsCallback(table: 'tl_news', target: 'config.onsubmit')]
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit')]
    #[AsCallback(table: 'tl_faq', target: 'config.onsubmit')]
    #[AsCallback(table: 'tl_article', target: 'config.onsubmit')]
    #[AsCallback(table: 'tl_content', target: 'config.onsubmit')]
    public function onSubmit(DataContainer $dc): void
    {
        $this->enqueue($dc);

        // New alias saved: the news, events and FAQs read on this page have new URLs
        if ('tl_page' === $dc->table && isset($this->aliasChangedPages[(int) $dc->id])) {
            unset($this->aliasChangedPages[(int) $dc->id]);
            $this->enqueueDependents((int) $dc->id);
        }
    }

    /**
     * Called before the record is deleted, so the URL can still be generated.
     */
    #[AsCallback(table: 'tl_page', target: 'config.ondelete')]
    #[AsCallback(table: 'tl_news', target: 'config.ondelete')]
    #[AsCallback(table: 'tl_calendar_events', target: 'config.ondelete')]
    #[AsCallback(table: 'tl_faq', target: 'config.ondelete')]
    #[AsCallback(table: 'tl_article', target: 'config.ondelete')]
    #[AsCallback(table: 'tl_content', target: 'config.ondelete')]
    #[AsCallback(table: 'tl_news_archive', target: 'config.ondelete')]
    #[AsCallback(table: 'tl_calendar', target: 'config.ondelete')]
    #[AsCallback(table: 'tl_faq_category', target: 'config.ondelete')]
    public function onDelete(DataContainer $dc): void
    {
        $this->enqueue($dc);

        // Contao deletes subpages and archive entries along with it, without callbacks for them
        if ($dc->id && ('tl_page' === $dc->table || ContentUrlCollector::isArchiveTable($dc->table))) {
            foreach ($this->urlCollector->getChildUrls($dc->table, (int) $dc->id) as $url) {
                $this->queue->add($url);
            }
        }
    }

    /**
     * Unpublished: report the URL while the record is still stored as published,
     * so search engines notice that it is gone. Publishing is covered by onSubmit.
     */
    #[AsCallback(table: 'tl_page', target: 'fields.published.save')]
    #[AsCallback(table: 'tl_news', target: 'fields.published.save')]
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.published.save')]
    #[AsCallback(table: 'tl_faq', target: 'fields.published.save')]
    #[AsCallback(table: 'tl_article', target: 'fields.published.save')]
    public function onPublishedSave(mixed $value, DataContainer $dc): mixed
    {
        if (!$value && $this->getStoredValue($dc, 'published')) {
            $this->enqueue($dc);
        }

        return $value;
    }

    /**
     * Content element shown or hidden: the page, news or event has changed.
     */
    #[AsCallback(table: 'tl_content', target: 'fields.invisible.save')]
    public function onInvisibleSave(mixed $value, DataContainer $dc): mixed
    {
        if ((bool) $value !== (bool) $this->getStoredValue($dc, 'invisible')) {
            $this->enqueue($dc);
        }

        return $value;
    }

    /**
     * A new alias makes the old URL invalid: report it while the old alias is still stored.
     */
    #[AsCallback(table: 'tl_page', target: 'fields.alias.save')]
    #[AsCallback(table: 'tl_news', target: 'fields.alias.save')]
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.alias.save')]
    #[AsCallback(table: 'tl_faq', target: 'fields.alias.save')]
    public function onAliasSave(mixed $value, DataContainer $dc): mixed
    {
        $stored = (string) $this->getStoredValue($dc, 'alias');

        // An empty value is generated from the title by a later callback: the old URL is reported as well
        if ('' !== $stored && (string) $value !== $stored) {
            $this->enqueue($dc);

            // Old URLs of the news, events and FAQs read on this page; onSubmit adds the new ones
            if ('tl_page' === $dc->table) {
                $this->enqueueDependents((int) $dc->id);
                $this->aliasChangedPages[(int) $dc->id] = true;
            }
        }

        return $value;
    }

    private function enqueueDependents(int $pageId): void
    {
        foreach ($this->urlCollector->getDependentUrls($pageId) as $url) {
            $this->queue->add($url);
        }
    }

    private function enqueue(DataContainer $dc): void
    {
        if (!$dc->id || !$this->isWatched($dc->table)) {
            return;
        }

        foreach ($this->urlCollector->getUrlsFor($dc->table, (int) $dc->id) as $url) {
            $this->queue->add($url);
        }
    }

    private function getStoredValue(DataContainer $dc, string $column): mixed
    {
        if (!$dc->id || !$this->isWatched($dc->table)) {
            return null;
        }

        $value = $this->connection->fetchOne(
            'SELECT ' . $this->connection->quoteSingleIdentifier($column) . ' FROM ' . $dc->table . ' WHERE id = ?',
            [(int) $dc->id]
        );

        return false === $value ? null : $value;
    }

    private function isWatched(string $table): bool
    {
        return ContentUrlCollector::supports($table) || ContentUrlCollector::supportsOwned($table);
    }
}
