<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 5/31/2018
 * Time: 2:47 PM
 */
##################################################################################################################
# This page has the functionality for both viewing and saving the Data Resolution popup for the locking/esignature
# history. This is largely a retooled dataResolutionPopup.php file from REDCap.
##################################################################################################################

require_once('LockDataQuality.php');

// These globals are REDCap variables that will be present since External Modules are loaded within REDCap
global $Proj,$lang, $require_change_reason;
$_POST['record'] = html_entity_decode(urldecode($_POST['record']), ENT_QUOTES);

$rule_id = $_POST['rule_id'];
$action = $_POST['action'];
$field = $_POST['field_name'].($_POST['instrument'] != "" ? "_".$_POST['instrument'] : "");
$event_id = $_POST['event_id'];
$record = label_decode($_POST['record']);
$instance = is_numeric($_POST['instance']) ? (int)$_POST['instance'] : 1;
$instrument = $_POST['instrument'];
$project_id = $_POST['pid'];

// Instantiate DataQuality object
$lq = new \Vanderbilt\ShowLockingLanguageExternalModule\LockDataQuality();

// Set title of dialog (different based on if using Field Comment Log or DQ Resolution)
$title = RCView::img(array('style'=>'vertical-align:middle;', 'src'=>'balloons.png')) .
	RCView::span(array('style'=>'vertical-align:middle;'),
		($data_resolution_enabled == '1' ? $lang['dataqueries_141'] : $lang['dataqueries_137'])
	);

// Display data cleaner history table of this field
if ($_POST['action'] == 'view')
{
	if (isset($_POST['existing_record']) && !$_POST['existing_record']) {
		// Set button text needed (DO NOT TRANSLATE)
		$saveAndOpenBtn = ($data_resolution_enabled == '1') ? "Save and then open Field Comment Log" : "Save and then open Data Resolution Pop-up";
		// Set instructions/warning text
		$popupInstr = ($data_resolution_enabled == '1') ? $lang['dataqueries_166'] : $lang['dataqueries_165'];
		// If record has not been saved yet, then give user message to first save the record
		$content = 	RCView::div(array('style'=>'margin:5px 0 25px;'),
				RCView::img(array( 'src'=>'exclamation_orange.png')) .
				RCView::b("{$lang['global_03']}{$lang['colon']} ") . $popupInstr
			) .
			RCView::div(array('style'=>'text-align:right;'),
				RCView::button(array('class'=>"jqbutton", 'style'=>'padding: 0.4em 0.8em !important;margin-right:3px;', 'onclick'=>"
							appendHiddenInputToForm('scroll-top', $(window).scrollTop());
							appendHiddenInputToForm('dqres-fld','$field');
							dataEntrySubmit(this);return false;"), $saveAndOpenBtn
				) .
				RCView::button(array('class'=>"jqbutton", 'style'=>'padding: 0.4em 0.8em !important;', 'onclick'=>"$('#lock_data_resolution').dialog('close');"), $lang['global_53'])
			);
		$title = ($data_resolution_enabled == '1') ? $lang['dataqueries_168'] : $lang['dataqueries_167'];
	} else {
		// Display the full history of this record's field + form for adding more comments/data queries
		$content = $lq->displayFieldDataResHistory($record, $event_id, $field, $_POST['rule_id'], $instance);
	}
	## Output JSON
	print json_encode_rc(array('content'=>$content, 'title'=>$title));
}


// Save new data cleaner values for this field
elseif ($_POST['action'] == 'save')
{
	// Determine the status to set
	if (in_array($_POST['status'], array('OPEN','CLOSED','VERIFIED','DEVERIFIED'))) {
		$dr_status = $_POST['status'];
	} elseif ((isset($_POST['response_requested']) && $_POST['response_requested'])
		|| (isset($_POST['response']) && $_POST['response'])) {
		$dr_status = 'OPEN';
	} else {
		$dr_status = '';
	}

	// Set subquery for rule_id/field
	$rule_id = "";
	$non_rule = ($field == '') ? "" : "1";
	if (is_numeric($_POST['rule_id'])) {
		// Determine if custom rule contains one field in logic
		$ruleContainsOneField = $lq->ruleContainsOneField($_POST['rule_id']);
		if ($ruleContainsOneField !== false) {
			// Custom rule with one field in logic (so consider it rule-less as field-level)
			$field = $ruleContainsOneField;
			$non_rule = "1";
		} else {
			// Custom rule-level (multiple fields)
			$rule_id = $_POST['rule_id'];
			$non_rule = "";
		}
	}

	// If any files were uploaded but deleted before final submission, then make sure we delete these from the edocs table
	if (isset($_POST['delete_doc_id']) && $_POST['delete_doc_id'] != '')
	{
		$delete_docs_ids = explode(",", $_POST['delete_doc_id']);
		// Delete from table (i.e. set delete field to NOW)
		$sql = "update redcap_edocs_metadata set delete_date = '".NOW."' where project_id = " . PROJECT_ID . "
				and delete_date is null and doc_id in (" . prep_implode($delete_docs_ids) . ")";
		db_query($sql);
	}

	// If query was just closed BUT a file was uploaded in the response, then delete that file
	if ($dr_status == 'CLOSED' && isset($_POST['upload_doc_id']) && is_numeric($_POST['upload_doc_id']))
	{
		// Delete from table (i.e. set delete field to NOW)
		$sql = "update redcap_edocs_metadata set delete_date = '".NOW."' where project_id = " . PROJECT_ID . "
				and delete_date is null and doc_id = '" . db_escape($_POST['upload_doc_id']) . "'";
		db_query($sql);
	}

	// Insert new or update existing
	$sql = "insert into redcap_data_quality_status (rule_id, non_rule, project_id, record, event_id, field_name, query_status, assigned_user_id, instance)
			values (".checkNull($rule_id).", ".checkNull($non_rule).", " . PROJECT_ID . ", '" . db_escape($record) . "',
			$event_id, " . checkNull($field) . ", ".checkNull($dr_status).", ".checkNull($_POST['assigned_user_id']).", '" . db_escape($instance) . "')
			on duplicate key update query_status = ".checkNull($dr_status).", status_id = LAST_INSERT_ID(status_id)";
	if (db_query($sql))
	{
		// Get cleaner_id
		$status_id = db_insert_id();
		// Get current user's ui_id
		$userInitiator = User::getUserInfo(USERID);
		// Add new row to data_resolution_log
		$sql = "insert into redcap_data_quality_resolutions (status_id, ts, user_id, response_requested,
				response, comment, current_query_status, upload_doc_id)
				values ($status_id, '".NOW."', ".checkNull($userInitiator['ui_id']).",
				".checkNull($_POST['response_requested']).", ".checkNull($_POST['response']).",
				".checkNull(trim(label_decode($_POST['comment']))).", ".checkNull($dr_status).", ".checkNull($_POST['upload_doc_id']).")";
		if (db_query($sql)) {
			// Success, so return content via JSON to redisplay with new changes made
			$res_id = db_insert_id();
			## SET RETURN ELEMENTS
			// Set balloon icon
			if ($dr_status == 'OPEN' && $_POST['response'] == '') {
				$icon = 'balloon_exclamation.gif';
				if ($_POST['send_back']) {
					$drw_log = "Send data query back for further attention";
				} elseif ($_POST['reopen_query']) {
					$drw_log = "Reopen data query";
				} else {
					$drw_log = "Open data query";
				}
			} elseif ($dr_status == 'OPEN' && $_POST['response'] != '') {
				$icon = 'balloon_exclamation_blue.gif';
				$drw_log = "Respond to data query";
			} elseif ($dr_status == 'CLOSED') {
				$icon = 'balloon_tick.gif';
				$drw_log = "Close data query";
			} elseif ($dr_status == 'VERIFIED') {
				$icon = 'tick_circle.png';
				$drw_log = "Verified data value";
			} elseif ($dr_status == 'DEVERIFIED') {
				$icon = 'exclamation_red.png';
				$drw_log = "De-verified data value";
			} else {
				$icon = 'balloon_left.png';
				$drw_log = "Add field comment";
			}
			// Get total number of open data issues
			$queryStatuses = $lq->countDataResIssues();
			$issuesOpen = $queryStatuses['OPEN'];
			// Get number of comments that this issue current has
			$dataIssuesThisRecordEvent = $lq->getDataIssuesByRule($_POST['rule_id'], $record, $event_id);
			//print_r($dataIssuesThisRecordEvent);
			$num_comments = ($field == '') ? $dataIssuesThisRecordEvent[$record][$event_id]['num_comments']
				: $dataIssuesThisRecordEvent[$record][$event_id][$field]['num_comments'];
			## Output JSON
			print json_encode_rc(array('res_id'=>$res_id, 'icon'=>APP_PATH_IMAGES.$icon,
				'num_issues'=>$issuesOpen, 'num_comments'=>$num_comments, 'title'=>$title, 'tsNow'=>DateTimeRC::format_ts_from_ymd(NOW),
				'data_resolution_enabled'=>$data_resolution_enabled));
			## Logging
			$logDataValues = json_encode_rc(array('res_id'=>$res_id,'record'=>$record,'event_id'=>$event_id,
				'field'=>$field,'rule_id'=>$_POST['rule_id'],'comment'=>trim(label_decode($_POST['comment']))));
			// Set event_id in query string for logging purposes only
			$_GET['event_id'] = $event_id;
			// Log it
			Logging::logEvent($sql,"redcap_data_quality_resolutions","MANAGE",$record,$logDataValues,$drw_log);
		} else {
			// ERROR!
			exit('0');
		}
	}
}