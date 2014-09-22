<?php
$extensionPath = t3lib_extMgm::extPath('linkservice');
return array(
	'tx_linkservice_linkrefresh' => $extensionPath . 'scheduler/class.tx_linkservice_linkrefresh.php',
	'tx_linkservice_httpheadquery' => $extensionPath . 'lib/class.tx_linkservice_httpheadquery.php',
);
