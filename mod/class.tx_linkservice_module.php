<?php

class tx_linkservice_module extends t3lib_SCbase {
    public function init() {
        global $LANG;
        $LANG->includeLLFile('EXT:linkservice/mod/locallang.xlf');
        parent::init();
    }

    public function menuConfig() {
        global $LANG;
        $this->MOD_MENU = array(
            'function' => array(
                'errors_here' => $LANG->getLL('errors_here'), 
                'errors_subtree' => $LANG->getLL('errors_subtree'), 
            )
        );
        parent::menuConfig();
    }

    public function main() {
		global $BE_USER, $LANG, $BACK_PATH, $TCA_DESCR, $TCA, $CLIENT, $TYPO3_CONF_VARS, $TYPO3_DB;

		$this->pageinfo = t3lib_BEfunc::readPageAccess($this->id, $this->perms_clause);
		$access = is_array($this->pageinfo) ? 1 : 0;
		$this->pageId = $this->pageinfo['uid'];

		if (($this->id && $access) || ($BE_USER->user['admin'] && !$this->id)) {
			// Draw the header.
			$this->doc = t3lib_div::makeInstance('template');
			$this->doc->backPath = $BACK_PATH;
			$this->doc->form = '<form action="mod.php?M=web_txvalidateurlsM1" method="POST">';
			// JavaScript
			$this->doc->JScode = '
				<script language="javascript" type="text/javascript">
					script_ended = 0;
					function jumpToUrl(URL)
					{
						document.location = URL;
					}
				</script>
			';
			$this->doc->postCode = '
				<script language="javascript" type="text/javascript">
					script_ended = 1;
					if (top.fsMod) top.fsMod.recentIds["web"] = 0;
				</script>
			';

			$headerSection = $this->doc->getHeader('pages', $this->pageinfo, $this->pageinfo['_thePath']) . '<br />' . $LANG->sL('LLL:EXT:lang/locallang_core.xml:labels.path') . ': ' . t3lib_div::fixed_lgd_cs($this->pageinfo['_thePath'], 50);

			$this->content .= $this->doc->startPage($LANG->getLL('title'));
			$this->content .= $this->doc->header($LANG->getLL('title'));
			$this->content .= $this->doc->spacer(5);
			$this->content .= $this->doc->section('', $this->doc->funcMenu($headerSection, t3lib_BEfunc::getFuncMenu($this->id, 'SET[function]', $this->MOD_SETTINGS['function'], $this->MOD_MENU['function'])));
			$this->content .= $this->doc->divider(5);

			// Render content:
			$this->moduleContent();

			// ShortCut
			if ($BE_USER->mayMakeShortcut()) {
				$this->content .= $this->doc->spacer(20) . $this->doc->section('', $this->doc->makeShortcutIcon('id', implode(',', array_keys($this->MOD_MENU)), $this->MCONF['name']));
			}

			$this->content .= $this->doc->spacer(10);
			$this->content .= '</div>';
		}
		else {
			// If no access or if ID == zero
			$this->doc = t3lib_div::makeInstance('template');
			$this->doc->backPath = $BACK_PATH;
			$this->content .= $this->doc->startPage($LANG->getLL('title'));
			$this->content .= $this->doc->header($LANG->getLL('title'));
			$this->content .= $this->doc->spacer(5);
			$this->content .= $this->doc->spacer(10);
		}
	}

    public function printContent() {
        $this->content .= $this->doc->endPage();
        echo $this->content;
    }

    protected function moduleContent() {
        switch ($this->MOD_SETTINGS['function']) {
        case 'errors_subtree':
            $this->errorsSubtree();
            break;

        case 'errors_here':
        default:
            $this->errorsHere();
            break;
        }
    }

    protected function errorsHere() {
        $sql = "SELECT log.*, pages.title
                FROM tx_linkservice_log AS log
                LEFT JOIN pages ON log.pid = pages.uid
                WHERE log.pid = ".intval($this->pageId)."
                ORDER BY pid";

        $this->renderErrors($sql);
    }

    protected function errorsSubtree() {
        $sql = "SELECT log.*, pages.title
                FROM tx_linkservice_log AS log 
                LEFT JOIN pages ON log.pid = pages.uid 
                WHERE log.pid IN (".$this->getTree($this->pageId).")
                ORDER BY pid";

        $this->renderErrors($sql);
    }

    protected function renderErrors($sql) {
        global $TYPO3_DB, $TCA, $LANG;

        $c = array();
        $rs = $TYPO3_DB->sql_query($sql);
        $backUrl = 'mod.php?id='.$this->pageId.'&M=web_txlinkserviceM1';
        
        $c[] = '<table>';
        $c[] = '<thead>';
        $c[] = '<tr>';
        $c[] = '<th>'.$LANG->getLL('link').'</th>';
        $c[] = '<th>'.$LANG->sL('LLL:EXT:lang/locallang_general.xlf:LGL.description').'</th>';
        $c[] = '<th>'.$LANG->sL('LLL:EXT:lang/locallang_general.xlf:LGL.starttime').'</th>';
        $c[] = '</tr>';
        $c[] = '</thead>';
        $c[] = '<tbody>';

        mb_internal_encoding("UTF-8");
        $old_pid = 0;
        $old_uid = 0;

        while ($r = $TYPO3_DB->sql_fetch_assoc($rs)) {
            // Rotating new page
            if ($old_pid <> $r['pid']) {
                $anchor = $r['table_name'].'_'.$r['pid'];
                $editLink = 'alt_doc.php?returnUrl='.urlencode($backUrl.'#'.$anchor).'&edit[pages]['.$r['pid'].']=edit';
                $c[] = '<td colspan="3"><a id="'.$anchor.'" href="'.$editLink.'"><h2>'.$r['title'].' ('.$r['pid'].')</h2></a></td>';

                // New line
                $c[] = '</tr><tr>';
                $old_pid = $r['pid'];
            }

            // Rotating new element
            if ($old_uid <> $r['record_uid']) {
                // Displaying what kind of field we are dealing with
                // Make a link to the element
                $anchor = $r['table_name'].'_'.$r['field_name'].'_'.$r['record_uid'];
                $editLink = 'alt_doc.php?returnUrl='.urlencode($backUrl.'#'.$anchor).'&edit['.$r['table_name'].']['.$r['record_uid'].']=edit';
                
                $c[] = '<td colspan="3" nowrap="nowrap"><a id="'.$anchor.'" href="'.$editLink.'">';
                $c[] = '<h4>';

                // Displaying language for the element
                $flag = $this->getLanguageFlag($r['table_name'], $r['record_uid']);
                if ($flag <> 'default') {
                    $c[] = '<span class="t3-icon t3-icon-flags t3-icon-flags-'.$flag.' t3-icon-'.$flag.'"></span>';
                }
                
                $c[] = $LANG->sL($TCA[$r['table_name']]['ctrl']['title']).'/'.$LANG->sL($TCA[$r['table_name']]['columns'][$r['field_name']]['label']).$r['record_uid'];
                $c[] = '</h4>';
                $c[] = '</a></td>';

                // New line
                $c[] = '</tr><tr>';
                $old_uid = $r['record_uid'];
            }


            // Color if any?
            if ($color = $this->getMessageTypeColor($r)) {
                $c[] = '<tr style="background-color: '.$color.';">';
            }
            else {
                $c[] = '<tr>';
            }

            // Render the link part. 
            // We cap the size of the displayed link to 70 chars not to clutter the ourput
            if (mb_strlen($r['link']) > 70) {
                $link_title = mb_substr($r['link'], 0, 66) . '...';
            }
            else {
                $link_title = $r['link'];
            }
            $c[] = '<td nowrap="nowrap"><a href="'.$r['link'].'">'.$link_title.'</a></td>';

            // Showing the message
            // We cap the size of the message to 110 chars
            if (mb_strlen($r['message']) > 110) {
                $message_show = mb_substr($r['message'], 0, 106) . '...';
                $c[] = '<td><span title="'.$r['message'].'">'.$message_show.'</span></td>';
            }
            else { 
                $c[] = '<td>'.$r['message'].'</td>';
            }

            // Rendering a simple understandable time
            $c[] = '<td>'.strftime("%Y-%m-%d&nbsp;%H:%M", $r['checktime']).'</td>';
            $c[] = '</tr>';
        }
        $c[] = '</tbody>';
        $c[] = '</table>';
        
        $this->content .= implode("\n", $c);
    }

	/**
	 * Get the pagetree from current page and down.
	 *
	 * @param int $uid - The uid of current page.
	 * @return array - Array with uids of pages.
	 */
	protected function getTree($uid) {
        global $TYPO3_DB;
		$pT = t3lib_div::makeInstance('t3lib_pageTree');
		$pT->init();
		$pT->getTree($uid, 99, '');
		$ids = $pT->ids;
		return implode(',',$ids);
	}

    private $elements_flags = array();
    private $flags = array();

    protected function getLanguageFlag($table, $element) {
        global $TYPO3_DB, $TCA;

        // Populate the langs if they are not
        if (count($this->flags) == 0) {
            $this->flags[0] = 'default';

            $rs = $TYPO3_DB->sql_query("SELECT uid, flag FROM sys_language");
            while (list($uid, $flag) = $TYPO3_DB->sql_fetch_row($rs)) {
                $this->flags[$uid] = $flag;
            }
        }

        // Select the element, if we no not know the language already
        if (!isset($this->element_flags[$table][$element])) {

            // Check if the table even has language support
            if ($languageField = $TCA[$table]['ctrl']['languageField']) {
                $rs = $TYPO3_DB->sql_query("SELECT $languageField FROM $table WHERE uid = $element");
                list($sys_language_uid) = $TYPO3_DB->sql_fetch_row($rs);
                $this->element_flags[$table][$element] = $this->flags[$sys_language_uid];
            }
            // We have not
            else {
                $this->element_flags[$table][$element] = 'default';
            }
        }
        
        return $this->element_flags[$table][$element];
    }

    protected function getMessageTypeColor($record) {
        // Missing
        if (preg_match('/\(4[0-1][0-9]\)/', $record['message'])) {
            return '#eee';
        }
        // Error
        else if (preg_match('/\(5[0-1][0-9]\)/', $record['message'])) {
            return '#ff0909';
        }
        // Some redirect
        else if (preg_match('/\(30[123]\)/', $record['message'])) {
            return '#fffe91';
        }
        // Other stuff
        else {
            return '';
        }
    }
}

