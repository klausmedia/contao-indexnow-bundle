<?php

declare(strict_types=1);

namespace KlausMedia\IndexNowBundle\Controller;

use KlausMedia\IndexNowBundle\IndexNow\Queue;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves the key file https://<domain>/<key>.txt, with which the search
 * engines check that the reports come from the owner of the domain. Route in
 * config/routes.yaml.
 */
#[AsController]
class KeyFileController
{
    public function __construct(private readonly Queue $queue)
    {
    }

    public function __invoke(Request $request, string $key): Response
    {
        foreach ($this->queue->getRootsForHost($request->getHost()) as $root) {
            // Compared in PHP: the comparison of MySQL ignores the case
            if ($root['key'] === $key) {
                $response = new Response($key, Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
                $response->setPublic();
                $response->setMaxAge(3600);

                return $response;
            }
        }

        throw new NotFoundHttpException();
    }
}
