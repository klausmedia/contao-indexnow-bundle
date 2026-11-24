<?php

declare(strict_types=1);

namespace KlausMedia\IndexNowBundle\Callback;

use Contao\CoreBundle\DataContainer\ClipboardManager;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use KlausMedia\IndexNowBundle\IndexNow\ContentUrlCollector;
use KlausMedia\IndexNowBundle\IndexNow\Queue;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Reports pages, news, events, FAQs, articles and content elements that are
 * copied, moved, restored from the recycle bin ("Wiederherstellen") or set
 * back to an older version.
 *
 * Contao calls oncut and onrestore_version only afterwards. The old place or
 * old URL is therefore taken in onload, which runs before the action in the
 * same request.
 */
class MoveRestoreCallback
{
    public function __construct(
        private readonly ContentUrlCollector $urlCollector,
        private readonly Queue $queue,
        private readonly RequestStack $requestStack,
        #[Autowire(service: 'contao.data_container.clipboard_manager')]
        private readonly ClipboardManager $clipboardManager,
    ) {
    }

    /**
     * Before moving or restoring a version: report the URLs at the old place.
     */
    #[AsCallback(table: 'tl_page', target: 'config.onload')]
    #[AsCallback(table: 'tl_news', target: 'config.onload')]
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload')]
    #[AsCallback(table: 'tl_faq', target: 'config.onload')]
    #[AsCallback(table: 'tl_article', target: 'config.onload')]
    #[AsCallback(table: 'tl_content', target: 'config.onload')]
    public function onLoad(DataContainer|null $dc = null): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $dc || null === $request || !$this->isWatched((string) $dc->table)) {
            return;
        }

        $ids = match (true) {
            'cut' === $request->query->get('act') => [$request->query->get('id')],
            'cutAll' === $request->query->get('act') => $this->clipboardManager->getIds($dc->table),
            'tl_version' === $request->request->get('FORM_SUBMIT') => [$request->query->get('id')],
            default => [],
        };

        foreach ($ids as $id) {
            if ((int) $id > 0) {
                $this->report($dc->table, (int) $id);
            }
        }
    }

    /**
     * After moving: the URLs at the new place, e.g. another website or
     * another archive with a different reader page.
     */
    #[AsCallback(table: 'tl_page', target: 'config.oncut')]
    #[AsCallback(table: 'tl_news', target: 'config.oncut')]
    #[AsCallback(table: 'tl_calendar_events', target: 'config.oncut')]
    #[AsCallback(table: 'tl_faq', target: 'config.oncut')]
    #[AsCallback(table: 'tl_article', target: 'config.oncut')]
    #[AsCallback(table: 'tl_content', target: 'config.oncut')]
    public function onCut(DataContainer $dc): void
    {
        if ($dc->id && $this->isWatched($dc->table)) {
            $this->report($dc->table, (int) $dc->id);
        }
    }

    /**
     * Copies of pages, articles and news are unpublished (doNotCopy) and are
     * skipped until they are published; copied content elements are visible
     * right away and change their page.
     */
    #[AsCallback(table: 'tl_page', target: 'config.oncopy')]
    #[AsCallback(table: 'tl_news', target: 'config.oncopy')]
    #[AsCallback(table: 'tl_calendar_events', target: 'config.oncopy')]
    #[AsCallback(table: 'tl_faq', target: 'config.oncopy')]
    #[AsCallback(table: 'tl_article', target: 'config.oncopy')]
    #[AsCallback(table: 'tl_content', target: 'config.oncopy')]
    public function onCopy(int|string $insertId, DataContainer $dc): void
    {
        if ($this->isWatched((string) $dc->table)) {
            $this->add($this->urlCollector->getUrlFor($dc->table, (int) $insertId));
        }
    }

    /**
     * Restored from the recycle bin: called for every restored record,
     * including the subpages, news or content elements deleted with it.
     *
     * @param array<string, mixed> $row
     */
    #[AsCallback(table: 'tl_page', target: 'config.onundo')]
    #[AsCallback(table: 'tl_news', target: 'config.onundo')]
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onundo')]
    #[AsCallback(table: 'tl_faq', target: 'config.onundo')]
    #[AsCallback(table: 'tl_article', target: 'config.onundo')]
    #[AsCallback(table: 'tl_content', target: 'config.onundo')]
    public function onUndo(string $table, array $row): void
    {
        if (!empty($row['id']) && $this->isWatched($table)) {
            $this->add($this->urlCollector->getUrlFor($table, (int) $row['id']));
        }
    }

    /**
     * Older version restored: the URL with the restored data (the old one was
     * reported in onLoad).
     */
    #[AsCallback(table: 'tl_page', target: 'config.onrestore_version')]
    #[AsCallback(table: 'tl_news', target: 'config.onrestore_version')]
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onrestore_version')]
    #[AsCallback(table: 'tl_faq', target: 'config.onrestore_version')]
    #[AsCallback(table: 'tl_article', target: 'config.onrestore_version')]
    #[AsCallback(table: 'tl_content', target: 'config.onrestore_version')]
    public function onRestoreVersion(string $table, int|string $id): void
    {
        if ($this->isWatched($table)) {
            $this->add($this->urlCollector->getUrlFor($table, (int) $id));
        }
    }

    /**
     * The URL of the record; for a page also its subpages, which move with it.
     */
    private function report(string $table, int $id): void
    {
        $this->add($this->urlCollector->getUrlFor($table, $id));

        if ('tl_page' === $table) {
            foreach ($this->urlCollector->getChildUrls($table, $id) as $url) {
                $this->queue->add($url);
            }
        }
    }

    private function add(string|null $url): void
    {
        if (null !== $url) {
            $this->queue->add($url);
        }
    }

    private function isWatched(string $table): bool
    {
        return ContentUrlCollector::supports($table) || ContentUrlCollector::supportsOwned($table);
    }
}
