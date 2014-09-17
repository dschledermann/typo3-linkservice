<?php

/** 
 * Registering class to scheduler
 */
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['tx_linkservice_linkrefresh'] = array(
	'extension' => $_EXTKEY,
	'title' => 'Link refresher',
	'description' => 'This task will traverse and refresh external links.',
);
