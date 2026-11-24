<?php

declare(strict_types=1);

namespace KlausMedia\IndexNowBundle\Callback;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;

/**
 * Generates the IndexNow key of a website root if the field is left empty and
 * checks a key entered by hand against the rules of the protocol.
 */
#[AsCallback(table: 'tl_page', target: 'fields.indexnowKey.save')]
class KeySaveCallback
{
    public const PATTERN = '/^[a-zA-Z0-9-]{8,128}$/';

    public function __invoke(mixed $value): string
    {
        $value = trim((string) $value);

        if ('' === $value) {
            return bin2hex(random_bytes(16));
        }

        if (!preg_match(self::PATTERN, $value)) {
            throw new \RuntimeException($GLOBALS['TL_LANG']['tl_page']['indexnowKeyInvalid'] ?? 'Invalid IndexNow key.');
        }

        return $value;
    }
}
