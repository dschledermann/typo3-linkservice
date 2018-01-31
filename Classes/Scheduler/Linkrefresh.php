<?php

namespace Dschledermann\Linkservice\Scheduler;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Cache\CacheManager;


class Linkrefresh extends \TYPO3\CMS\Scheduler\Task\AbstractTask {
	// The configuration from ext_conf_template.txt
    protected $extConf;

    // The http query object.
    // @SEE lib/class.tx_linkservice_httpheadquery.php
    protected $httpQuery = null;

    // The cache
    protected $cache = null;

    public function execute() {
        // Extracting config
        $this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['linkservice']);
        $this->extConf['field_linkservice'] = explode(' ', $this->extConf['field_linkservice']);

        // Setting up crawler
        $this->httpQuery = GeneralUtility::makeInstance('Dschledermann\\Linkservice\\Http\\Headquery');
        $this->httpQuery->http_timeout = $this->extConf['http_timeout'];

        // Setting up cache
		$this->cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('linkservice');
        $this->cache->collectGarbage();
        
        // Get a random field to work on.
        // We have no better algoritm at the moment for grabbing the right 
        // field to work on. 
        // @FIXME: We could possible balance it out using a record-count

        $random_index = rand(0, count($this->extConf['field_linkservice']) - 1);
        $field = $this->extConf['field_linkservice'][$random_index];
        $this->workOnField($field);

        return true;
    }

    protected function workOnField($field_id) {
        global $TYPO3_DB;

        // Get proper table and fieldname
        list($table, $field) = explode('.', $field_id);

        $records_per_run = $this->extConf['records_per_run'];

        // How old should the check be before refresh?
        // If the record has never been checked before the field_status-record will not exist and "lastcheck" will be NULL
        $renew_lower_limit = time() - $this->extConf['field_validity_period'] * 3600;

        // Select records having links in the desired field.
        // Join with field status to find out which records.
        $sql = "SELECT uid, pid, $field_id, field_status.lastcheck
                FROM $table
                INNER JOIN tx_linkservice_field_status AS field_status 
                ON     uid = field_status.record_uid
                  AND  field_status.field_name = '$field'
                  AND  field_status.table_name = '$table'
                WHERE ($field_id LIKE '%<link http%' OR $field_id LIKE '%<a href=%' )
                  AND  field_status.lastcheck < $renew_lower_limit
                  AND  deleted = 0
                  AND  hidden = 0

                ORDER BY field_status.lastcheck ASC
                LIMIT $records_per_run;";

        $rs = $TYPO3_DB->sql_query($sql);

        while (list($uid, $pid, $body, $lastcheck) = $TYPO3_DB->sql_fetch_row($rs)) {
 
			// This gives both the entire linkcontent ie. <a href="http://www.example.com/some/path" or <link http://www.example.com/some/path>
			// And the list of links ie. http://www.example.com/some/path
			// We need to have both to have a more robust link replacement.
            list($linkcontexts, $links) = $this->resolveLinksOnBody($body);

            $replacements = array();

            // Confirm each link
            foreach($links as $i => $link) {

                // Only process if the link yields any result
                $response = $this->refreshLink(html_entity_decode($link));

                // See if we even have a replacement link
                if ($response->isPermanentRedirect()) {
					$replacements[$i] = str_replace($link, htmlentities($response->location), $linkcontexts[$i]);
                }

                // If we have logging turned on - log if we see anything interesting
                if ($this->extConf['generate_report']) {
                    if ($response->isPermanentRedirect() ||  $response->isTemporaryRedirect()) {
                        $this->logToPage($pid, $table, $field, $uid, $link, $response->statusCode, $response->location);
                    }

                    if ($response->isUnavailable() ||  $response->isError()) {
                        $this->logToPage($pid, $table, $field, $uid, $link, $response->statusCode, '', $response->exception_message);
                    }
                }
            }

            // If any links has changed, then replace back into the body
            if (count($replacements)) {
                foreach ($replacements as $i => $replacement) {
                    $body = str_replace($linkcontexts[$i], $replacement, $body);
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
    protected function refreshLink($link) {
        // Peek at the cache first
        $hash = sha1($link);
        $response = $this->cache->get($hash);

        // If not in cache use the crawler to refresh link
        if ($response === false) {
            if ($response = $this->httpQuery->submitUrl($link)) {
                $this->cache->set($hash, $response, array(), $this->extConf['link_validity_period'] * 3600);
            }
        }

        return $response;
    }

    /**
     * Regex the body and get all both t3 and html links
     */
    protected function resolveLinksOnBody($body) {
        // Get TYPO3 RTE style links 
        preg_match_all('/<link (http[^ >]+)>/i', $body, $t3links);

        // Get HTML style precoded links
        preg_match_all('/<a href="(http[^"]+)"/i', $body, $htmllinks);

        return array(array_merge($t3links[0], $htmllinks[0]), array_merge($t3links[1], $htmllinks[1]));
    }

	protected function markFieldChecked($uid, $field, $table, $lastcheck) {
		global $TYPO3_DB;

		// If lastcheck has a value, the record was checked before and hence we do an update
		$TYPO3_DB->exec_UPDATEquery('tx_linkservice_field_status', 
									"record_uid = $uid AND table_name = '$table' AND field_name = '$field'", 
									array('lastcheck' => time()));
    }

    protected function logToPage($pid, $table, $field, $uid, $link, $status, $location = '', $exception_message = '') {
        $record = array(
            'pid' => $pid,
            'table_name' => $table,
            'field_name' => $field,
            'record_uid' => $uid,
            'link' => $link,
            'http_status' => $status,
            'location' => $location,
            'exception_message' => $exception_message,
            'checktime' => time(),
        );

        $GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_linkservice_log', $record);
    }
}

