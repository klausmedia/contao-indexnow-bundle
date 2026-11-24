<?php

declare(strict_types=1);

// Values the bundle remembers between cron runs (see src/Cron/ScheduledContentCron.php). No backend module.
$GLOBALS['TL_DCA']['tl_km_indexnow_state'] = [
    'config' => [
        'sql' => [
            'keys' => [
                'name' => 'primary',
            ],
        ],
    ],

    'fields' => [
        'name' => [
            'sql' => ['type' => 'string', 'length' => 64, 'default' => ''],
        ],
        'value' => [
            'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
        ],
    ],
];
