<?php

// Fields
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['tstamp'] = ['Changed', 'Time of the last change'];
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['root'] = ['Website', 'Website root whose key is used'];
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['url'] = ['URL', 'The address to report'];
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['hash'] = ['Checksum', 'SHA-1 of the URL'];

// Buttons
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['submit'] = ['Send now', 'Send all queued URLs to IndexNow right away'];
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['enqueueAll'] = ['Queue all URLs', 'Add all URLs of the sitemap of each website to the queue'];

// Messages
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['manualInfo'] = 'Automatic sending is switched off for: %s. These URLs are only reported with "Send now".';
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['nothingToSend'] = 'The queue is empty.';
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['sent'] = '%d URL(s) reported to IndexNow.';
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['retry'] = '%d URL(s) not reported (IndexNow unreachable or busy). They stay in the queue.';
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['failed'] = '%d URL(s) rejected by IndexNow. See the system log for details.';
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['noRoots'] = 'No website root has IndexNow enabled.';
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['noDomain'] = 'Website root ID %d has no domain.';
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['invalidSitemap'] = '%s is not a valid sitemap.';
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['unreachable'] = '%s cannot be reached: %s';
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['enqueued'] = '%s: %d URL(s) added to the queue.';
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['cronNeverRan'] = 'The cron job for automatic sending has not run yet. This is normal right after the installation. If this message stays, automatic sending is not possible here: report the URLs with "Send now" and switch off "Send automatically" in the website root.';
$GLOBALS['TL_LANG']['tl_km_indexnow_queue']['cronStale'] = 'The cron job for automatic sending has not run since %s. Without a cron job, report the URLs with "Send now" and switch off "Send automatically" in the website root.';
