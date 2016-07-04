<?php

/** 
 * Registering scheduler
 */
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['Dschledermann\Linkservice\Scheduler\Linkrefresh'] = array(
	'extension' => $_EXTKEY,
	'title' => 'Link refresher',
	'description' => 'Traverse and refresh external links.',
);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['Dschledermann\Linkservice\Scheduler\Clearlog'] = array(
	'extension' => $_EXTKEY,
	'title' => 'Link log cleaner',
	'description' => 'Clean out stale link refresh log records.',
);


// Enable cache for extension.
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['linkservice'])) {
	// Set cache to use string for frontend.
	$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['linkservice'] = array(
		'frontend' => 'TYPO3\\CMS\\Core\\Cache\\Frontend\\VariableFrontend',
		'backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\Typo3DatabaseBackend',
		'options' => array());
}
