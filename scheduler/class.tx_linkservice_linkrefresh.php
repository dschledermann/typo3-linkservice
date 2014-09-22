<?php

class tx_linkservice_linkrefresh extends tx_scheduler_Task {
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
        $this->httpQuery = t3lib_div::makeInstance('tx_linkservice_httpheadquery');
        $this->httpQuery->http_timeout = $this->extConf['http_timeout'];

        // Setting up cache
        try {
            $this->cache = $GLOBALS['typo3CacheManager']->getCache('linkservice');
        } 
        catch (t3lib_cache_exception_NoSuchCache $e) {
            $this->cache = $GLOBALS['typo3CacheFactory']->create(
                'linkservice',
                $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['linkservice']['frontend'],
                $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['linkservice']['backend'],
                $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['linkservice']['options']
            );
        }
        
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
        $sql = "SELECT uid, $field_id, field_status.lastcheck
                FROM $table
                LEFT JOIN tx_linkservice_field_status AS field_status 
                ON     uid = field_status.record_uid
                  AND  field_status.field_name = '$field'
                  AND  field_status.table_name = '$table'
                WHERE ($field_id LIKE '%<link http%' OR $field_id LIKE '%<a href=%' )
                  AND (field_status.lastcheck < $renew_lower_limit || field_status.lastcheck IS NULL)
                  AND deleted = 0
                  AND hidden = 0

                ORDER BY field_status.lastcheck ASC
                LIMIT $records_per_run;";

        $rs = $TYPO3_DB->sql_query($sql);

        while (list($uid, $body, $lastcheck) = $TYPO3_DB->sql_fetch_row($rs)) {
            $links = $this->resolveLinksOnBody($body);
            $replacementlinks = array();

            // Confirm each link
            foreach($links as $link) {
                $replacementlink = $this->refreshLink(html_entity_decode($link));

                // Clean up the double-&amp; encodings.
                $replacementlink = preg_replace('/&amp(;amp)*(%3b|;)/i', '&', $replacementlink);
                $replacementlink = htmlentities($replacementlink);

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
    protected function refreshLink($link) {
        return $this->_refreshLink($link, array($link));
    }
    
    protected function _refreshLink($link, $previous_links = array()) {
        // If we have descended 10 request into a redirect chain,
        // we do not wish to continue, and are leaving the original link in place
        // @TODO: Maybe we should report on loops.
        if (count($previous_links) > 10) {
            return $previous_links[0];
        }

        // Peek at the cache first
        $hash = sha1($link);
        $new_link = $this->cache->get($hash);

        // If not in cache use the crawler to refresh link
        if ($new_link === false) {
            $this->httpQuery->submitUrl($link);

            // We had a new link
            if ($this->httpQuery->isPermanentRedirect()) {
                $new_link = $this->httpQuery->getLocation();
                
                // Have we not seen this link before
                // we need to resolve where this leads, maybe we a not done
                if ( ! in_array($new_link, $previous_links)) {
                    
                    // Remember this new link and try deeper
                    array_push($previous_links, $link);
                    $new_link = $this->refreshLink($new_link, $previous_links);
                }

                // We have seen this link before, so we must be in a loop.
                // This is an error
                // Do not continue, but return the original link
                // @TODO: Maybe we should report on loops.
                else {
                    return $previous_links[0];
                }
            }
            // Errors or no change.
            else {
                $new_link = $link;
            }
            $this->cache->set($hash, $new_link, array(), $this->extConf['link_validity_period'] * 3600);
        }
        
        return $new_link;
    }

    /**
     * Regex the body and get all both t3 and html links
     */
    protected function resolveLinksOnBody($body) {
        // Get TYPO3 RTE style links 
        preg_match_all('/<link (http[^ >]+)/i', $body, $matches);
        $t3links = $matches[1];

        // Get HTML style precoded links
        preg_match_all('/<a href="(http[^"]+)"/i', $body, $matches);
        $htmllinks = $matches[1];

        $total_links = array_unique(array_merge($t3links, $htmllinks));
        return $total_links;
    }

    protected function markFieldChecked($uid, $field, $table, $lastcheck) {
        global $TYPO3_DB;

        // If lastcheck has a value, the record was checked before and hence we do an update
        if ($lastcheck) {
            $TYPO3_DB->exec_UPDATEquery('tx_linkservice_field_status', 
                                        "record_uid = $uid AND table_name = '$table' AND field_name = '$field'", 
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

