<?php

class ext_update {
    public function main() {
		global $TYPO3_DB;

        $content = "<h1>Migrating class names</h1>";

		$rs = $TYPO3_DB->exec_SELECTquery("uid, serialized_task_object", "tx_scheduler_task", "serialized_task_object LIKE '%:\"tx_linkservice_%'");

		while (list($uid, $serialized) = $TYPO3_DB->sql_fetch_row($rs)) {
			$content .= "<h2>Task #" . $uid . "</h2>";

			if (preg_match('/26:"tx_linkservice_linkrefresh"/', $serialized)) {
				$content .= '<p>tx_linkservice_linkrefresh => Dschledermann\Linkservice\Scheduler\Linkrefresh</p>';
				$serialized = str_replace('26:"tx_linkservice_linkrefresh"', '47:"Dschledermann\Linkservice\Scheduler\Linkrefresh"', $serialized);
			}

			if (preg_match('/23:"tx_linkservice_clearlog"/', $serialized)) {
				$content .= '<p>tx_linkservice_clearlog => Dschledermann\Linkservice\Scheduler\Clearlog</p>';
				$serialized = str_replace('23:"tx_linkservice_clearlog"', '44:"Dschledermann\Linkservice\Scheduler\Clearlog"', $serialized);
			}

			$TYPO3_DB->exec_UPDATEquery('tx_scheduler_task', "uid = $uid", array('serialized_task_object' => $serialized));
		}

        return $content;
    }

    public function access() {
        return true;
    }
}

