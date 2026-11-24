<?php

declare(strict_types=1);

namespace KlausMedia\IndexNowBundle\IndexNow;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Routing\Page\PageRegistry;
use Contao\FaqCategoryModel;
use Contao\FaqModel;
use Contao\Model;
use Contao\NewsArchiveModel;
use Contao\NewsModel;
use Contao\PageModel;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Returns the absolute frontend URL of a page, news, event or FAQ, if it may
 * be reported: not protected, not noindex, a real detail page.
 */
class ContentUrlCollector
{
    public const TABLES = ['tl_page', 'tl_news', 'tl_calendar_events', 'tl_faq'];

    // Page types without own content
    private const SKIPPED_PAGE_TYPES = ['root', 'folder', 'redirect', 'forward', 'logout', 'error_401', 'error_403', 'error_404', 'error_503'];

    private const MODELS = [
        'tl_page' => PageModel::class,
        'tl_news' => NewsModel::class,
        'tl_calendar_events' => CalendarEventsModel::class,
        'tl_faq' => FaqModel::class,
    ];

    // Tables without own URL: a change is reported as a change of the page, news or event they belong to
    public const OWNED_TABLES = ['tl_article', 'tl_content'];

    // Nesting depth of content elements (e.g. elements inside an element group)
    private const MAX_DEPTH = 10;

    // Archive table (with the reader page in jumpTo) => entry table, whose URLs contain the reader page URL
    private const READER_ARCHIVES = [
        'tl_news_archive' => 'tl_news',
        'tl_calendar' => 'tl_calendar_events',
        'tl_faq_category' => 'tl_faq',
    ];

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ContentUrlGenerator $urlGenerator,
        private readonly Connection $connection,
        private readonly PageRegistry $pageRegistry,
    ) {
    }

    public static function supports(string $table): bool
    {
        return \in_array($table, self::TABLES, true);
    }

    public static function supportsOwned(string $table): bool
    {
        return \in_array($table, self::OWNED_TABLES, true);
    }

    /**
     * Like getUrl(), but an article or content element returns the URL of the
     * published page, news or event it belongs to.
     */
    public function getUrlFor(string $table, int $id, bool $requirePublished = true): string|null
    {
        if (self::supports($table)) {
            return $this->getUrl($table, $id, $requirePublished);
        }

        $owner = $this->resolveOwner($table, $id);

        // A hidden page does not need to be reported because of one of its elements
        return null !== $owner ? $this->getUrl($owner[0], $owner[1], true) : null;
    }

    /**
     * URLs of the published news, events and FAQs whose archive uses the page
     * as reader page: they change with the alias of the page. Subpages are not
     * affected, as Contao stores the full path in their own alias.
     *
     * @return list<string>
     */
    public function getDependentUrls(int $pageId): array
    {
        $this->framework->initialize();

        // The model registry may still hold the page with its old alias
        PageModel::findById($pageId)?->refresh();

        $existing = $this->connection->createSchemaManager()->listTableNames();
        $urls = [];

        foreach (self::READER_ARCHIVES as $archiveTable => $table) {
            if (!\in_array($archiveTable, $existing, true) || !\in_array($table, $existing, true)) {
                continue;
            }

            $ids = $this->connection->fetchFirstColumn(
                "SELECT e.id FROM $table e INNER JOIN $archiveTable a ON a.id = e.pid WHERE a.jumpTo = ? AND e.published = 1",
                [$pageId]
            );

            foreach ($ids as $id) {
                if (null !== ($url = $this->getUrl($table, (int) $id))) {
                    $urls[] = $url;
                }
            }
        }

        return $urls;
    }

    public static function isArchiveTable(string $table): bool
    {
        return isset(self::READER_ARCHIVES[$table]);
    }

    /**
     * URLs Contao deletes together with a record, without calling the delete
     * callbacks for them: the subpages of a page, the entries of a news
     * archive, calendar or FAQ category. Call before deleting.
     *
     * @return list<string>
     */
    public function getChildUrls(string $table, int $id): array
    {
        if ('tl_page' === $table) {
            $childTable = 'tl_page';
            $ids = [];
            $parents = [$id];

            for ($depth = 0; [] !== $parents && $depth < 50; ++$depth) {
                $parents = array_map('intval', $this->connection->fetchFirstColumn(
                    'SELECT id FROM tl_page WHERE pid IN (?)',
                    [$parents],
                    [ArrayParameterType::INTEGER]
                ));
                array_push($ids, ...$parents);
            }
        } elseif (self::isArchiveTable($table)) {
            $childTable = self::READER_ARCHIVES[$table];
            $ids = $this->connection->fetchFirstColumn("SELECT id FROM $childTable WHERE pid = ?", [$id]);
        } else {
            return [];
        }

        $urls = [];

        foreach ($ids as $childId) {
            if (null !== ($url = $this->getUrl($childTable, (int) $childId))) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * @return array{0: string, 1: int}|null table and ID of the record with the URL
     */
    public function resolveOwner(string $table, int $id): array|null
    {
        for ($depth = 0; $depth < self::MAX_DEPTH; ++$depth) {
            if (self::supports($table)) {
                return [$table, $id];
            }

            $row = match ($table) {
                'tl_article' => $this->connection->fetchAssociative("SELECT 'tl_page' AS ptable, pid FROM tl_article WHERE id = ?", [$id]),
                'tl_content' => $this->connection->fetchAssociative('SELECT ptable, pid FROM tl_content WHERE id = ?', [$id]),
                default => false,
            };

            if (false === $row || !$row['pid']) {
                return null;
            }

            $table = '' !== (string) $row['ptable'] ? (string) $row['ptable'] : 'tl_article';
            $id = (int) $row['pid'];
        }

        return null;
    }

    /**
     * @param bool $requirePublished false for published records whose stop date was reached
     */
    public function getUrl(string $table, int $id, bool $requirePublished = true): string|null
    {
        if (!self::supports($table) || !class_exists(self::MODELS[$table])) {
            return null;
        }

        $this->framework->initialize();

        /** @var class-string<Model> $class */
        $class = self::MODELS[$table];
        $model = $class::findById($id);

        if (null === $model) {
            return null;
        }

        // The model registry may still hold the values from before saving
        $model->refresh();

        if (!$this->isReportable($model, $requirePublished)) {
            return null;
        }

        if ($requirePublished && !$this->isPublished($model)) {
            return null;
        }

        // The generator caches URLs per request by the data of the record only. A news
        // under a renamed reader page or a subpage of a moved page would get its old URL.
        $this->urlGenerator->reset();

        try {
            $url = $this->urlGenerator->generate($model, [], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (ExceptionInterface) {
            // e.g. news archive without a reader page
            return null;
        }

        return str_starts_with($url, 'http') ? $url : null;
    }

    private function isReportable(Model $model, bool $requirePublished): bool
    {
        if (str_contains((string) $model->robots, 'noindex')) {
            return false;
        }

        if ($model instanceof PageModel) {
            if (\in_array($model->type, self::SKIPPED_PAGE_TYPES, true) || 'map_never' === $model->sitemap) {
                return false;
            }

            // Inherits the protection of the parent pages
            $model->loadDetails();

            // Pages without HTML content, e.g. news feeds (same check as in the sitemap of Contao)
            return !$model->protected
                && $this->pageRegistry->supportsContentComposition($model)
                && 'html' === $this->pageRegistry->getRoute($model)->getDefault('_format');
        }

        if ($model instanceof NewsModel || $model instanceof CalendarEventsModel) {
            // Links to other pages or external URLs have no own detail page
            if ('default' !== $model->source) {
                return false;
            }

            $archive = $model instanceof NewsModel
                ? NewsArchiveModel::findById($model->pid)
                : CalendarModel::findById($model->pid);

            if (null === $archive || $archive->protected) {
                return false;
            }
        } else {
            $archive = FaqCategoryModel::findById($model->pid);
        }

        return $this->isReaderPageReportable($model, (int) $archive?->jumpTo, $requirePublished);
    }

    private function isReaderPageReportable(Model $model, int $pageId, bool $requirePublished): bool
    {
        $page = $pageId > 0 ? PageModel::findById($pageId) : null;

        if (null === $page) {
            return false;
        }

        $page->refresh();

        // As in the sitemap of Contao; otherwise the robots setting of the entry overrides that of the page
        if ('noindex,nofollow' === $page->robots || str_contains((string) ($model->robots ?: $page->robots), 'noindex')) {
            return false;
        }

        if ($requirePublished && !$this->isPublished($page)) {
            return false;
        }

        // Inherits the protection of the parent pages
        $page->loadDetails();

        return !$page->protected;
    }

    private function isPublished(Model $model): bool
    {
        if (!$model->published) {
            return false;
        }

        $now = time();
        $start = (string) $model->start;
        $stop = (string) $model->stop;

        return ('' === $start || (int) $start <= $now) && ('' === $stop || (int) $stop > $now);
    }
}
