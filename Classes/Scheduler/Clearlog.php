<?php

namespace Dschledermann\Linkservice\Scheduler;

class Clearlog extends \TYPO3\CMS\Scheduler\Task\AbstractTask {

    // The configuration from ext_conf_template.txt
    protected $extConf;

    public function execute() {

        // Extracting config
        $this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['linkservice']);
        $this->extConf['field_linkservice'] = explode(' ', $this->extConf['field_linkservice']);

		// Clean out old log entries
        $GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_linkservice_log', 'checktime < '.(time() - 3600 * $this->extConf['log_retention']));

		// Make sure that check-records exists for all followed field
		// Select all relevant fields where the check-record is missing

        foreach ($this->extConf['field_linkservice'] as $fieldId) {
			$this->createFields($fieldId);
		}

		return true;
    }

	protected function createFields($fieldId) {
		global $TYPO3_DB;

        // Get proper table and fieldname
        list($table, $field) = explode('.', $fieldId);

		$sql = "SELECT uid
                FROM $table
                LEFT JOIN tx_linkservice_field_status AS field_status 
                ON     uid = field_status.record_uid
                  AND  field_status.field_name = '$field'
                  AND  field_status.table_name = '$table'
                WHERE ($field_id LIKE '%<link http%' OR $field_id LIKE '%<a href=%' )
                  AND  field_status.lastcheck IS NULL
                  AND  deleted = 0
                  AND  hidden = 0";

		$rs = $TYPO3_DB->sql_query($sql);

		while(list($uid) = $TYPO3_DB->sql_fetch_row($rs)) {
			$record = array(
                'lastcheck' => 0,
                'record_uid' => $uid,
                'table_name' => $table,
                'field_name' => $field,
            );
            $TYPO3_DB->exec_INSERTquery('tx_linkservice_field_status', $record);
		}
	}
}
