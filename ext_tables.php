<?php

if (TYPO3_MODE == 'BE') {
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
		'web',
		'txlinkserviceM',
		'',
		null,
		[
			'icon' => 'EXT:linkservice/mod/module_icon.gif',
			'labels' => 'LLL:EXT:linkservice/Lang/locallang_mod.xlf',
			'routeTarget' => \Dschledermann\Linkservice\Module::class . '::main',
			'access' => 'user,group',
			'name' => 'web_txlinkserviceM',
		]
	);
}
