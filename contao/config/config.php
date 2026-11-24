<?php

declare(strict_types=1);

use KlausMedia\IndexNowBundle\Backend\QueueModule;

// System → IndexNow: queue with the buttons "Jetzt senden" and "Alle URLs einreihen"
$GLOBALS['BE_MOD']['system']['indexnow'] = [
    'tables'     => ['tl_km_indexnow_queue'],
    'submit'     => [QueueModule::class, 'submit'],
    'enqueueAll' => [QueueModule::class, 'enqueueAll'],
];
