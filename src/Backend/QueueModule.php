<?php

declare(strict_types=1);

namespace KlausMedia\IndexNowBundle\Backend;

use Contao\Config;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Exception\InvalidRequestTokenException;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Date;
use Contao\Message;
use KlausMedia\IndexNowBundle\IndexNow\Queue;
use KlausMedia\IndexNowBundle\IndexNow\SitemapEnqueuer;
use KlausMedia\IndexNowBundle\IndexNow\Submitter;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Actions of the backend module "System → IndexNow" (see contao/config/config.php).
 * Public, because Contao gets the handlers of the key=… actions from the container.
 */
#[Autoconfigure(public: true)]
class QueueModule
{
    private const TABLE = 'tl_km_indexnow_queue';

    // The cron job runs every 5 minutes; without a server cron job, the web cron
    // of Contao only runs when the website is visited
    private const CRON_STALE_AFTER = 3600;

    public function __construct(
        private readonly Queue $queue,
        private readonly Submitter $submitter,
        private readonly SitemapEnqueuer $sitemapEnqueuer,
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $router,
        private readonly TranslatorInterface $translator,
        #[Autowire(service: 'contao.csrf.token_manager')]
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        #[Autowire(param: 'contao.csrf_token_name')]
        private readonly string $csrfTokenName,
    ) {
    }

    /**
     * Shows in the list which websites are only sent by hand.
     */
    #[AsCallback(table: self::TABLE, target: 'config.onload')]
    public function onLoad(DataContainer|null $dc = null): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || $request->query->has('act') || $request->query->has('key')) {
            return;
        }

        $titles = $this->queue->getManualRootTitles();

        if ([] !== $titles) {
            $this->message()->addInfo($this->trans('manualInfo', [implode(', ', $titles)]));
        }

        $this->checkCron();
    }

    /**
     * Some hosting plans have no cron job, and the web cron of Contao may be
     * switched off: then nothing is sent automatically.
     */
    private function checkCron(): void
    {
        if (!$this->queue->hasAutoRoots()) {
            return;
        }

        $lastRun = $this->queue->getLastCronRun();

        if (null === $lastRun) {
            $this->message()->addError($this->trans('cronNeverRan'));

            return;
        }

        if ($lastRun < time() - self::CRON_STALE_AFTER) {
            $format = (string) $this->framework->getAdapter(Config::class)->get('datimFormat');
            $this->message()->addError($this->trans('cronStale', [$this->framework->getAdapter(Date::class)->parse($format, $lastRun)]));
        }
    }

    /**
     * "Jetzt senden": sends the whole queue, also of websites without automatic submission.
     */
    public function submit(DataContainer $dc): RedirectResponse
    {
        $this->checkToken();

        if (0 === $this->queue->count()) {
            $this->message()->addInfo($this->trans('nothingToSend'));

            return $this->redirect();
        }

        $result = $this->submitter->submitQueue();

        // Only URLs of websites that no longer use IndexNow: they were dropped
        if (0 === $result['sent'] + $result['retry'] + $result['failed']) {
            $this->message()->addInfo($this->trans('nothingToSend'));
        }

        if ($result['sent'] > 0) {
            $this->message()->addConfirmation($this->trans('sent', [$result['sent']]));
        }

        if ($result['retry'] > 0) {
            $this->message()->addInfo($this->trans('retry', [$result['retry']]));
        }

        if ($result['failed'] > 0) {
            $this->message()->addError($this->trans('failed', [$result['failed']]));
        }

        return $this->redirect();
    }

    /**
     * "Alle URLs einreihen": queues all URLs of the sitemaps; sent with the next
     * cron job or with "Jetzt senden".
     */
    public function enqueueAll(DataContainer $dc): RedirectResponse
    {
        $this->checkToken();

        // A website root without domain is served for the domain of the backend
        $results = $this->sitemapEnqueuer->enqueueAll(null, $this->requestStack->getCurrentRequest()?->getHost());

        if ([] === $results) {
            $this->message()->addError($this->trans('noRoots'));

            return $this->redirect();
        }

        foreach ($results as $result) {
            foreach ($result['errors'] as $error) {
                $this->message()->addError(match ($error['code']) {
                    SitemapEnqueuer::ERROR_NO_DOMAIN => $this->trans('noDomain', [$result['root']]),
                    SitemapEnqueuer::ERROR_INVALID => $this->trans('invalidSitemap', [$error['url']]),
                    default => $this->trans('unreachable', [$error['url'], $error['message']]),
                });
            }

            if (null !== $result['sitemap']) {
                $this->message()->addConfirmation($this->trans('enqueued', [$result['sitemap'], $result['added']]));
            }
        }

        return $this->redirect();
    }

    /**
     * The buttons are links with the request token (rt), like the core's own actions.
     */
    private function checkToken(): void
    {
        $token = (string) $this->requestStack->getCurrentRequest()?->query->get('rt');

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($this->csrfTokenName, $token))) {
            throw new InvalidRequestTokenException('Invalid CSRF token. Please reload the page and try again.');
        }
    }

    private function redirect(): RedirectResponse
    {
        return new RedirectResponse($this->router->generate('contao_backend', ['do' => 'indexnow']));
    }

    /**
     * Contao outputs messages as HTML: titles, URLs from the sitemaps and error
     * texts are escaped (without encoding the entities Contao already stores).
     *
     * @param list<string|int> $parameters
     */
    private function trans(string $key, array $parameters = []): string
    {
        $parameters = array_map(
            static fn (string|int $value): string|int => \is_string($value) ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false) : $value,
            $parameters
        );

        return $this->translator->trans(self::TABLE . '.' . $key, $parameters, 'contao_' . self::TABLE);
    }

    /**
     * @return Adapter<Message>
     */
    private function message(): Adapter
    {
        return $this->framework->getAdapter(Message::class);
    }
}
