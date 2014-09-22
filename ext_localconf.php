<?php

/** 
 * Registering class to scheduler
 */
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['tx_linkservice_linkrefresh'] = array(
	'extension' => $_EXTKEY,
	'title' => 'Link refresher',
	'description' => 'This task will traverse and refresh external links.',
);

// Enable cache for extension.
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['linkservice'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['linkservice'] = array();
}

// Set cache to use string for frontend.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['linkservice'] = array(
    'frontend' => 't3lib_cache_frontend_StringFrontend',
    'backend' => 't3lib_cache_backend_DbBackend',
    'options' => array()
);


