<?php

declare(strict_types=1);

use Contao\CoreBundle\DataContainer\PaletteManipulator;

// IndexNow settings of a website root (see src/Callback/KeySaveCallback.php)
$GLOBALS['TL_DCA']['tl_page']['palettes']['__selector__'][] = 'indexnowEnable';
$GLOBALS['TL_DCA']['tl_page']['subpalettes']['indexnowEnable'] = 'indexnowKey,indexnowAutoSubmit';

$GLOBALS['TL_DCA']['tl_page']['fields']['indexnowEnable'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['submitOnChange' => true, 'tl_class' => 'clr'],
    'sql'       => ['type' => 'boolean', 'default' => false],
];

$GLOBALS['TL_DCA']['tl_page']['fields']['indexnowKey'] = [
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['maxlength' => 128, 'decodeEntities' => true, 'tl_class' => 'w50'],
    'sql'       => ['type' => 'string', 'length' => 128, 'default' => ''],
];

// Off: the cron job leaves the URLs in the queue, they are sent in System → IndexNow
$GLOBALS['TL_DCA']['tl_page']['fields']['indexnowAutoSubmit'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'default'   => true,
    'eval'      => ['tl_class' => 'w50 m12'],
    'sql'       => ['type' => 'boolean', 'default' => true],
];

PaletteManipulator::create()
    ->addLegend('indexnow_legend', 'publish_legend', PaletteManipulator::POSITION_BEFORE, true)
    ->addField('indexnowEnable', 'indexnow_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('root', 'tl_page')
    ->applyToPalette('rootfallback', 'tl_page');
