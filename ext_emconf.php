<?php

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Linkservice - link refresher',
	'description' => 'Refreshes links in elements by issuing HEAD-request to them. Redirect are followed and updated back into the tables.',
	'category' => 'Configuration',
	'shy' => '',
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'module' => 'mod',
	'state' => 'stable',
	'internal' => '',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'author' => 'Daniel Schledermann',
	'author_email' => 'daniel@linkfactory.dk',
	'author_company' => 'Linkfactory A/S',
	'version' => '3.1.0',
	'constraints' => array(
		'depends' => array(
			'typo3' => '6.2.0-6.2.99',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'suggests' => array(
	),
);

?>