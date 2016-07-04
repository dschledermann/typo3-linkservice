<?php
$SOBE = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Dschledermann\Linkservice\Module');
$SOBE->init();
$SOBE->main();
$SOBE->printContent();
