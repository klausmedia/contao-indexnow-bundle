<?php

// Fields
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['tstamp'] = ['Geändert', 'Zeitpunkt der letzten Änderung'];
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['root'] = ['Website', 'Startpunkt, dessen Schlüssel verwendet wird'];
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['url'] = ['URL', 'Die zu meldende Adresse'];
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['hash'] = ['Prüfsumme', 'SHA-1 der URL'];

// Buttons
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['submit'] = ['Jetzt senden', 'Alle URLs der Warteschlange sofort an IndexNow senden'];
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['enqueueAll'] = ['Alle URLs einreihen', 'Alle URLs aus der Sitemap jeder Website in die Warteschlange stellen'];

// Messages
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['manualInfo'] = 'Automatisches Senden ist ausgeschaltet für: %s. Diese URLs werden nur mit „Jetzt senden“ gemeldet.';
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['nothingToSend'] = 'Die Warteschlange ist leer.';
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['sent'] = '%d URL(s) an IndexNow gemeldet.';
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['retry'] = '%d URL(s) nicht gemeldet (IndexNow nicht erreichbar oder überlastet). Sie bleiben in der Warteschlange.';
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['failed'] = '%d URL(s) von IndexNow abgelehnt. Details im Systemlog.';
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['noRoots'] = 'Kein Startpunkt hat IndexNow aktiviert.';
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['noDomain'] = 'Startpunkt ID %d hat keine Domain.';
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['invalidSitemap'] = '%s ist keine gültige Sitemap.';
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['unreachable'] = '%s ist nicht erreichbar: %s';
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['enqueued'] = '%s: %d URL(s) in die Warteschlange gestellt.';
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['cronNeverRan'] = 'Der Cronjob für das automatische Senden ist noch nicht gelaufen. Direkt nach der Installation ist das normal. Bleibt der Hinweis, ist hier kein automatisches Senden möglich: die URLs mit „Jetzt senden“ melden und im Startpunkt „Automatisch senden“ ausschalten.';
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['cronStale'] = 'Der Cronjob für das automatische Senden ist seit %s nicht mehr gelaufen. Ohne Cronjob die URLs mit „Jetzt senden“ melden und im Startpunkt „Automatisch senden“ ausschalten.';
