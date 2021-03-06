<?php

namespace Dschledermann\Linkservice;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Dschledermann\Linkservice\Report\Query;

class Module extends \TYPO3\CMS\Backend\Module\BaseScriptClass {
    public function init() {
        global $LANG;
        $LANG->includeLLFile('EXT:linkservice/Lang/locallang_modlabels.xlf');
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

		$this->MCONF['name'] = 'web_txlinkserviceM';
		$this->MCONF['access'] = 'user,group';
		$this->MCONF['script'] = '_DISPATCH';
		$this->MLANG['default']['tabs_images']['tab'] = 'module_icon.gif';
		$this->MLANG['default']['ll_ref'] = 'LLL:EXT:linkservice/Lang/locallang_mod.xlf';

		$LANG->includeLLFile('LLL:EXT:linkservice/Lang/locallang_mod.xlf');
		$LANG->includeLLFile('EXT:linkservice/Lang/locallang_modlabels.xlf');

		$this->init();
		$this->menuConfig();

		$this->pageinfo = BackendUtility::readPageAccess($this->id, $this->perms_clause);
		$access = is_array($this->pageinfo) ? 1 : 0;
		$this->pageId = $this->pageinfo['uid'];

		if (($this->id && $access) || ($BE_USER->user['admin'] && !$this->id)) {
			// Draw the header.
			$this->doc = GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Template\\DocumentTemplate');
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

			$headerSection = $this->doc->getHeader('pages', $this->pageinfo, $this->pageinfo['_thePath']) . '<br />' . $LANG->sL('LLL:EXT:lang/locallang_core.xml:labels.path') . ': ' . GeneralUtility::fixed_lgd_cs($this->pageinfo['_thePath'], 50);

			$this->content .= $this->doc->startPage($LANG->getLL('title'));
			$this->content .= $this->doc->header($LANG->getLL('title'));
			$this->content .= '<div style="padding-top: 5px;"></div>';
			$this->content .= $this->doc->section('', $this->doc->funcMenu($headerSection, BackendUtility::getFuncMenu($this->id, 'SET[function]', $this->MOD_SETTINGS['function'], $this->MOD_MENU['function'])));
			$this->content .= '<div style="padding-top: 5px;"></div>';

			// Render content:
			$this->moduleContent();

			// ShortCut
			if ($BE_USER->mayMakeShortcut()) {
				$this->content .= '<div style="padding-top: 20px;"></div>' . $this->doc->section('', $this->doc->makeShortcutIcon('id', implode(',', array_keys($this->MOD_MENU)), $this->MCONF['name']));
			}

			$this->content .= '<div style="padding-top: 10px;"></div>';
			$this->content .= '</div>';
		}
		else {
			// If no access or if ID == zero
			$this->doc = GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Template\\DocumentTemplate');
			$this->doc->backPath = $BACK_PATH;
			$this->content .= $this->doc->startPage($LANG->getLL('title'));
			$this->content .= $this->doc->header($LANG->getLL('title'));
			$this->content .= '<div style="padding-top: 15px;"></div>';
		}

		$this->printContent();
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
        $logSet = Query::getPageLog($this->pageId);
        $this->renderErrors($logSet);
    }

    protected function errorsSubtree() {
        $logSet = Query::getSubtreeLog($this->pageId);
        $this->renderErrors($logSet);
    }

    protected function renderErrors($logs) {
        global $TCA, $LANG;

        $c = array();
        $backUrl = $_SERVER['REQUEST_URI'];
        
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

        // Each first-level entry is a page. The key is the page id.
        // It will contain two entries:
        // - "title" which is the selected page title
        // - "tables" which is any table that holds records on the page

        foreach ($logs as $pid => $page_log) {

            // Adding the heading for the page
            $page_anchor = 'pages_'.$pid;
            $page_editLink = BackendUtility::getModuleUrl('record_edit') . '&returnUrl='.urlencode($backUrl.'#'.$page_anchor).'&edit[pages]['.$pid.']=edit';
            $c[] = '<tr>';
            $c[] = '<td colspan="3"><a id="'.$page_anchor.'" href="'.$page_editLink.'"><h2>'.$page_log['title'].' ('.$pid.')</h2></a></td>';
            $c[] = '</tr>';

            // Traverse each of the tables with records on the page
            foreach ($page_log['tables'] as $table_name => $records) {

                // Make a header for the table. Often there might only be one.
                $c[] = '<tr>';
                $c[] = '<td colspan="3"><h3>'.$LANG->sL($TCA[$table_name]['ctrl']['title']).'</h3></td>';
                $c[] = '</tr>';

                // Traverse each record on the table
                foreach ($records as $uid => $record) {

                    // Traverse each field that holds links reported.
                    // The overwelming possibility is that only one field is in use here.
                    foreach($record as $field_name => $field) {

                        // Make a header for the field/element
                        $anchor = $table_name.'_'.$field_name.'_'.$uid;
                        $editLink = BackendUtility::getModuleUrl('record_edit') . '&returnUrl='.urlencode($backUrl.'#'.$anchor).'&edit['.$table_name.']['.$uid.']=edit';

                        $c[] = '<tr>';
                        $c[] = '<td colspan="3"><h4><a id="'.$anchor.'" href="'.$editLink.'">#'.$LANG->sL($TCA[$table_name]['columns'][$field_name]['label']).$uid.'</a></h4></td>';
                        $c[] = '</tr>';

                        // Traverse each link structure in the field
                        // A link structure will have four fields:
                        // - "link" - the link in question
                        // - "message" - a summary on what is going on
                        // - "checktime" - a display of the time of detection
                        // - "type_color" - a color this row should be marked.
                        foreach ($field as $l) {

                            // Setting color only if the reporting states so
                            if ($l['type_color']) {
                                $c[] = '<tr style="background-color: '.$l['type_color'].'">';
                            }
                            else {
                                $c[] = '<tr>';
                            }

                            // Displaying capped link
                            if (mb_strlen($l['link']) > 70) {
                                $link_title = mb_substr($l['link'], 0, 66) . '...';
                            }
                            else {
                                $link_title = $l['link'];
                            }
                            $c[] = '<td><a href="'.$l['link'].'">'.$link_title.'</a></td>';


                            // Displaying capped message
                            if (mb_strlen($l['message']) > 110) {
                                $message_show = mb_substr($l['message'], 0, 106) . '...';
                                $c[] = '<td><span title="'.$l['message'].'">'.$message_show.'</span></td>';
                            }
                            else { 
                                $c[] = '<td>'.$l['message'].'</td>';
                            }

                            // The time is straight forward
                            $c[]= '<td>'.$l['checktime'].'</td>';
                            $c[] = '</tr>';
                        }
                    }
                }
            }
        }

        $c[] = '</tbody>';
        $c[] = '</table>';
        
        $this->content .= implode("\n", $c);
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
}

