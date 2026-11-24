<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;

// URLs waiting to be reported to IndexNow (see src/IndexNow/Queue.php),
// shown in System → IndexNow (see src/Backend/QueueModule.php)
$GLOBALS['TL_DCA']['tl_km_indexnow_queue'] = [
    'config' => [
        'dataContainer'       => DC_Table::class,
        'closed'              => true,
        'notEditable'         => true,
        'notCopyable'         => true,
        'backendSearchIgnore' => true,
        'sql' => [
            'keys' => [
                'id'   => 'primary',
                'hash' => 'unique',
                'root' => 'index',
            ],
        ],
    ],

    'list' => [
        'sorting' => [
            'mode'        => DataContainer::MODE_SORTABLE,
            'fields'      => ['tstamp', 'id'],
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields' => ['tstamp', 'url'],
            'format' => '<span class="label-date">[%s]</span> %s',
        ],
        // Actions in src/Backend/QueueModule.php; links with request token, never prefetched
        'global_operations' => [
            'submit' => [
                'href'       => 'key=submit',
                'attributes' => 'data-turbo-prefetch="false"',
                'primary'    => true,
            ],
            'enqueueAll' => [
                'href'       => 'key=enqueueAll',
                'attributes' => 'data-turbo-prefetch="false"',
            ],
            '-',
            'all',
        ],
        'operations' => [
            'delete',
            'show',
        ],
    ],

    'fields' => [
        'id' => [
            // Newest first within a day
            'flag' => DataContainer::SORT_DESC,
            'sql'  => ['type' => 'integer', 'unsigned' => true, 'autoincrement' => true],
        ],
        'tstamp' => [
            'sorting' => true,
            'flag'    => DataContainer::SORT_DAY_DESC,
            'eval'    => ['rgxp' => 'datim'],
            'sql'     => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
        ],
        // Website root whose key is used
        'root' => [
            'filter'     => true,
            'foreignKey' => 'tl_page.title',
            'sql'        => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
        ],
        // sha1 of the URL, keeps every URL only once in the queue
        'hash' => [
            'sql' => ['type' => 'string', 'length' => 40, 'fixed' => true, 'default' => ''],
        ],
        'url' => [
            'search' => true,
            'sql'    => ['type' => 'text', 'notnull' => false],
        ],
    ],
];
