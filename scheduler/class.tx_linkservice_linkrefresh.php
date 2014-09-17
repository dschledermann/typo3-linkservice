<?php

class tx_linkservice_linkrefresh extends tx_scheduler_Task {
    private $extConf;

    public function execute() {
        $this->getConf();
        $this->cleanStaleCacheRecords();

        // Get a random field to work on.
        // We have no better algoritm at the moment for grabbing the right 
        // field to work on. 
        // @FIXME: We could possible balance it out using a record-count

        list ($field) = array_rand($this->extConf['field_linkservice'], 1);
        $this->workOnField($field);
    }

    private function getConf() {
        $this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['linkservice']);
        $this->extConf['field_linkservice'] = explode(' ', $this->extConf['field_linkservice']);
    }

    private function cleanStaleCacheRecords() {
        global $TYPO3_DB;
        $sql = "DELETE FROM tx_linkservice_linkcache WHERE expires < UNIX_TIMESTAMP()";
        $TYPO3_DB->sql_query($sql);
    }

    private function workOnField($field_id) {
        global $TYPO3_DB;

        // Get proper table and fieldname
        list($table, $field) = explode('.', $field_id);

        $records_per_run = $this->extConf['records_per_run'];

        // Select records having links in the desired field.
        // Join with field status to find out which records.
        $sql = "SELECT uid, $field_id, field_status.lastcheck
                FROM $table
                LEFT JOIN tx_linkservice_field_status AS field_status ON uid = record_uid
                WHERE ( $field_id LIKE '%<link http%' OR $field_id LIKE '%<a href=%' )
                AND   field_name = '$field'
                AND   table_name = '$table'
                ORDER BY field_status.lastcheck ASC
                LIMIT $records_per_run";

        $rs = $TYPO3_DB->sql_query($sql);
        

        while (list($uid, $body, $lastcheck) = $TYPO3_DB->sql_fetch_row($rs)) {
            $links = $this->resolveLinksOnBody($body);
            $replacementlinks = array();

            // Confirm each link
            foreach($links as $link) {
                $replacementlink = $this->refreshLink($link);

                if ($replacementlink <> $link) {
                    $replacementlinks[$link] = $replacementlink;
                }
            }

            // If any links has changed, then replace back into the body
            if (count($replacementlinks)) {
                foreach ($replacementlinks as $link => $replacementlink) {
                    $body = str_replace($link, $replacementlink, $body);
                }

                $TYPO3_DB->exec_UPDATEquery($table, "uid = $uid", array($field => $body));
            }

            // Mark that we have been there and confirmed links on this field
            $this->markFieldChecked($uid, $field, $table, $lastcheck);
        }
    }
    
    /**
     * Try to refresh a link using http HEAD
     */
    private function refreshLink($link) {
        return $link;

        // What to do
        // @SEE: http://dk1.php.net/manual/en/function.fsockopen.php
    }

    /**
     * Regex the body and get all both t3 and html links
     */
    private function resolveLinksOnBody($body) {
        // Get TYPO3 RTE style links 
        preg_match_all('/<link (http[^ >]+)/i', $body, $matches);
        list ($t3links) = $matches[1];

        // Get HTML style precoded links
        preg_match_all('/<a href="(http[^"]+)"/i', $body, $matches);
        list ($htmllinks) = $matches[1];

        return array_unique(array_merge($t3links, $htmllinks));
    }

    private function markFieldChecked($uid, $field, $table, $lastcheck) {
        global $TYPO3_DB;
        
        // If lastcheck has a value, the record was checked before and hence we do an update
        if ($lastcheck) {
            $TYPO3_DB->exec_UPDATEquery('tx_linkservice_field_status', 
                                        "record_uid = $uid AND table_name = '$table_name' AND field_name = '$field_name'", 
                                        array('lastcheck' => time()));
        }

        // Otherwise insert a fresh new record
        else {
            $record = array(
                'lastcheck' => time(),
                'record_uid' => $uid,
                'table_name' => $table,
                'field_name' => $field,
            );
            $TYPO3_DB->exec_INSERTquery('tx_linkservice_field_status', $record);
        }
    }
}

