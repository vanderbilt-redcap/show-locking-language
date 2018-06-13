<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 6/6/2018
 * Time: 1:06 PM
 */
##################################################################################################################
# This class is an extension of the DataQuality REDCap class, mainly being used to generate our own Data Quality
# popup, and being used to bypass the pesky checks REDCap does to make sure you're trying to do data quality on
# a field in the project. The logging/esignature checks don't have field names in the project, so we make our own
##################################################################################################################

namespace Vanderbilt\ShowLockingLanguageExternalModule;

class LockDataQuality extends \DataQuality
{
	private $rules = null;
	// Display data resolution history in table format
	public function displayFieldDataResHistory($record, $event_id, $field, $rule_id='', $instance=1)
	{
		global $longitudinal, $lang, $table_pk_label, $Proj, $user_rights, $data_resolution_enabled, $double_data_entry, $field_comment_edit_delete;

		// append --# if DDE user
		$record .= ($double_data_entry && $user_rights['double_data'] != 0) ? "--".$user_rights['double_data'] : "";

		// Load all rules so we can use the rule number and label
		$this->loadRules();
		// Obtain data cleaner history  as array
		$drw_history = $this->getFieldDataResHistory($record, $event_id, $field, $rule_id, $instance);
		$drw_history_count = count($drw_history);

		// If using full DRW, then INTERWEAVE DATA HISTORY LOG into the comments
		if ($data_resolution_enabled == '2')
		{
			// Get data history log
			if ($field == '') {
				// If a rule with multiple fields, loop through all fields to get all their Data History
				$fieldsInLogic = array_keys(getBracketedFields($this->rules[$rule_id]['logic'], true, true, true));
				$dh_history = array();
				foreach ($fieldsInLogic as $thisDhField) {
					$dh_history_temp = \Form::getDataHistoryLog($record, $event_id, $thisDhField, $instance);
					// Reformat data values so that it is formatted as "field_name = "data values""
					foreach ($dh_history_temp as &$attr) {
						if ($Proj->isCheckbox($thisDhField)) $attr['value'] = nl2br(str_replace("\n\n", "\n", trim(br2nl($attr['value']))));
						$attr['value'] = "$thisDhField = '{$attr['value']}'";
					}
					unset($attr);
					// Merge into existing values
					$dh_history = array_merge($dh_history, $dh_history_temp);
				}
				// Now put DH back in chronological order now that we've merged them all
				$dh_datetimes = array();
				foreach ($dh_history as $attr) $dh_datetimes[] = $attr['ts'];
				array_multisort($dh_datetimes, SORT_REGULAR, $dh_history);
			} else {
				$dh_history = \Form::getDataHistoryLog($record, $event_id, $field, $instance);
				// Reformat data values so that it is formatted as "field_name = "data values""
				foreach ($dh_history as &$attr) {
					if ($Proj->isCheckbox($field)) $attr['value'] = nl2br(str_replace("\n\n", "\n", trim(br2nl($attr['value']))));
					$attr['value'] = "$field = '{$attr['value']}'";
				}
				unset($attr);
			}
			$dh_history_count = count($dh_history);
			$dh_history = array_values($dh_history);
			// Walk trough $drw_history and $dh_history chronologically and add to $drw_history_temp
			if ($dh_history_count > 0) {
				// Put merged info into $drw_history_temp, which we'll delete later
				$drw_history_temp = array();
				$drw_key = $dh_key = 0;
				// Loop
				for ($key = 0; $key < ($dh_history_count+$drw_history_count); $key++)
				{
					if (isset($dh_history[$dh_key]) && (!isset($drw_history[$drw_key]) || $dh_history[$dh_key]['ts'] <= $drw_history[$drw_key]['ts'])) {
						// Add data history event to array
						$drw_history_temp[] = array('ts'=>$dh_history[$dh_key]['ts'], 'user_id'=>$dh_history[$dh_key]['user'],
							'data_values'=>$dh_history[$dh_key]['value']);
						// Increment its key
						$dh_key++;
					} elseif (isset($drw_history[$drw_key]) && (!isset($dh_history[$dh_key]) || $dh_history[$dh_key]['ts'] > $drw_history[$drw_key]['ts'])) {
						// Add DRW comment to array and increment its key
						$drw_history_temp[] = $drw_history[$drw_key++];
					}
				}
				$drw_history = $drw_history_temp;
				unset($drw_history_temp);
			}
		}

		// Initialize variables
		$h = $r = $currentStatus = $currentResponded = $statusThisItem = '';
		$prevUserAttr = array();
		$num_row = 0;
		// Build rows of existing items in this thread
		if (!empty($drw_history))
		{
			// Loop through items in thread
			foreach ($drw_history as $attr) {
				// Increment number of DRW rows (exclude Data History rows)
				if (isset($attr['res_id'])) $num_row++;
				// Render row/section
				$r .= self::renderFieldDataResHistoryExistingSection($attr, $prevUserAttr, $num_row);
				// Get value of current status and last action's attributes
				if (isset($attr['query_status'])) {
					$prevUserAttr = $attr;
					$currentStatus = $prevUserAttr['query_status'];
					$statusThisItem = $prevUserAttr['current_query_status'];
					$currentResponded = $prevUserAttr['response'];
				}
			}
		}
		## Instructions
		// Set string for field name/label
		$fieldNameLabel = '';
		if ($field != '') {
			$fieldNameLabel = \RCView::div('',
				"{$lang['graphical_view_23']}{$lang['colon']} <b>$field</b>
								(\"" . strip_tags($Proj->metadata[$field]['element_label']) . "\") "
			);
		}
		// Set string for field name/label
		$ruleLabel = '';
		if ($rule_id != '') {
			$ruleLabel = 	\RCView::div('',
				"{$lang['dataqueries_14']}{$lang['colon']} " .
				\RCView::span(array('style'=>'color:#800000;'),
					"<b>" . $lang['dataqueries_14'] . " " . (is_numeric($rule_id) ? '#' : '') .
					$this->rules[$rule_id]['order'] . $lang['colon'] . "</b> " . $this->rules[$rule_id]['name']
				)
			);
		}
		// Query status label
		$queryStatusLabel = '';
		if ($data_resolution_enabled == '2')
		{
			if ($currentStatus == '') {
				$currentStatusText = $lang['dataqueries_217'];
				$currentStatusColor = 'gray';
				$currentStatusIcon = 'balloon_left_bw2.gif';
			} else {
				$currentStatusText = $lang['dataqueries_216'];
				if ($currentStatus == 'OPEN' && $currentResponded == '') {
					$currentStatusColor = '#C00000';
					$currentStatusIcon = 'balloon_exclamation.gif';
					$currentStatusText .= \RCView::span(array('style'=>'font-weight:normal;margin-left:5px;'), $lang['dataqueries_219']);
				} elseif ($currentStatus == 'OPEN' && $currentResponded != '') {
					$currentStatusColor = '#000066';
					$currentStatusIcon = 'balloon_exclamation_blue.gif';
					$currentStatusText .= \RCView::span(array('style'=>'font-weight:normal;margin-left:5px;'), $lang['dataqueries_218']);
				} elseif ($currentStatus == 'VERIFIED') {
					$currentStatusColor = 'green';
					$currentStatusIcon = 'tick_circle.png';
					$currentStatusText = $lang['dataqueries_220'];
				} elseif ($currentStatus == 'DEVERIFIED') {
					$currentStatusColor = '#800000';
					$currentStatusIcon = 'exclamation_red.png';
					$currentStatusText = $lang['dataqueries_222'];
				} else {
					$currentStatusColor = 'green';
					$currentStatusIcon = 'balloon_tick.gif';
					$currentStatusText = $lang['dataqueries_215'];
				}
			}
			$queryStatusLabel = \RCView::div('',
				$lang['dataqueries_214']." " .
				\RCView::img(array('src'=>$currentStatusIcon)) .
				\RCView::span(array('style'=>"font-weight:bold;color:$currentStatusColor;"),
					$currentStatusText
				)
			);
		}
		// Output instructions string
		$h .= 	\RCView::div(array('style'=>'margin:0 0 15px;'),
			// Instructions
			($data_resolution_enabled == '2'
				? 	// DRW instructxions
				\RCView::div(array('style'=>'text-align:right;margin:0 10px 5px;'),
					\RCView::img(array('src'=>'video_small.png')) .
					\RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;', 'onclick'=>"popupvid('data_resolution_workflow01.swf','".js_escape($lang['dataqueries_137'])."');"),
						$lang['global_80'] . " " . $lang['dataqueries_137']
					)
				) .
				$lang['dataqueries_129']
				: 	// Field Comment Log instructions
				$lang['dataqueries_154'] . " " .
				\RCView::a(array('href'=>APP_PATH_WEBROOT."DataQuality/field_comment_log.php?pid=".PROJECT_ID,
					'style'=>"text-decoration:underline;"), $lang['dataqueries_141']) . " " .
				$lang['dataqueries_258'] .
				// Add note about disabling editing/deleting field comments
				(($data_resolution_enabled == '1' && $field_comment_edit_delete) ? " " .
					\RCView::span(array('style'=>'color:#800000;'), $lang['dataqueries_287']) : '')
			) .
			// Record
			\RCView::div(array('style'=>'margin-top:10px;'),
				"{$table_pk_label}{$lang['colon']} &nbsp;" .
				($field == ''
					? \RCView::span(array('style'=>'font-size:13px;font-weight:bold;'),
						\RCView::escape($double_data_entry && $user_rights['double_data'] != 0 ? substr($record, 0, -3)  : $record)
					)
					: \RCView::a(array('href'=>APP_PATH_WEBROOT."DataEntry/index.php?pid=".PROJECT_ID."&instance=$instance&event_id=$event_id&id=".
						($double_data_entry && $user_rights['double_data'] != 0 ? substr($record, 0, -3)  : $record)
						."&page=".$Proj->metadata[$field]['form_name']."&fldfocus=$field#$field-tr", 'style'=>'font-size:13px;font-weight:bold;text-decoration:underline;'),
						\RCView::escape($double_data_entry && $user_rights['double_data'] != 0 ? substr($record, 0, -3)  : $record)
					)
				)
			) .
			// Event name (if longitudinal)
			(($longitudinal && isset($Proj->eventInfo[$event_id])) ? "<div class='dq_evtlabel'>{$lang['bottom_23']} <b>" . $Proj->eventInfo[$event_id]['name_ext'] . "</b></div>" : "") .
			// Rule
			$ruleLabel .
			// Field
			$fieldNameLabel .
			// Opened/Closed, etc.
			$queryStatusLabel
		);
		## Render SECTION HEADER as separate table
		$h .=
			// If query has not been opened and user has Respond-only rights, then don't show table header
			(($data_resolution_enabled == '2' && $user_rights['data_quality_resolution'] == '2' && empty($prevUserAttr)) ? '' :
				\RCView::table(array('id'=>'existingDCHistorySH','class'=>'form_border','cellspacing'=>'0','style'=>'table-layout:fixed;width:100%;'),
					// SECTION HEADER (only display if some rows exist already)
					\RCView::tr('',
						(!($data_resolution_enabled == '1' && $field_comment_edit_delete) ? '' :
							\RCView::td(array('class'=>'label_header','style'=>'padding:0;width:35px;'),
								''
							)
						) .
						\RCView::td(array('class'=>'label_header','style'=>'padding:5px 8px;width:140px;'),
							$lang['dataqueries_06']
						) .
						\RCView::td(array('class'=>'label_header','style'=>'padding:5px 8px;width:145px;'),
							$lang['global_17']
						) .
						\RCView::td(array('class'=>'label_header','style'=>'text-align:left;padding:5px 8px 5px 12px;'),
							($data_resolution_enabled == '1'
								// "Comments" header text
								? $lang['dataqueries_146']
								// "Comments and Details" header text
								: $lang['dataqueries_147']
							)
						)
					)
				)
			);
		// If field is provided, then get its form name
		if ($field != '') {
			$fieldForm = $Proj->metadata[$field]['form_name'];
			//$hasFormEditRights = ($user_rights['forms'][$fieldForm] == '1' || $user_rights['forms'][$fieldForm] == '3');
		}
		// Render whole thread as a table insider a scrollable div
		$h .=
			// Display existing thread
			($r == '' ? '' :
				\RCView::div(array('id'=>'existingDCHistoryDiv','style'=>'overflow-y:auto;'),
					\RCView::table(array('id'=>'existingDCHistory','class'=>'form_border','cellspacing'=>'0','style'=>'table-layout:fixed;width:100%;'),
						// Rows for EXISTING COMMENTS/ATTRIBUTES
						$r
					)
				)
			) .
			## Rows for adding NEW COMMENT/ATTRIBUTES
			// If using Field Comment Log
			((	$data_resolution_enabled == '1'
				// Or if using DR and responding to an open query
				|| ($data_resolution_enabled == '2' && $prevUserAttr['response_requested']
					&& ($user_rights['data_quality_resolution'] == '2' || $user_rights['data_quality_resolution'] == '3'
						|| $user_rights['data_quality_resolution'] == '5'))
				// Or if using DR and opening a query OR re-opening a closed query (with Open Query Only rights)
				|| ($data_resolution_enabled == '2' && $user_rights['data_quality_resolution'] == '4'
					&& (empty($prevUserAttr)
						|| $prevUserAttr['current_query_status'] == 'CLOSED' || $prevUserAttr['current_query_status'] == 'VERIFIED'
						|| $prevUserAttr['current_query_status'] == 'DEVERIFIED'))
				// Or if using DR and opening a query OR re-opening a closed query OR responding to an open query (as Open and Response rights)
				|| ($data_resolution_enabled == '2' && $user_rights['data_quality_resolution'] == '5'
					&& (empty($prevUserAttr) || $prevUserAttr['response_requested']
						|| $prevUserAttr['current_query_status'] == 'CLOSED' || $prevUserAttr['current_query_status'] == 'VERIFIED'
						|| $prevUserAttr['current_query_status'] == 'DEVERIFIED'))
				// Or if using DR and opening a query OR closing an open query OR re-opening a closed query (as Open, Close, Response rights)
				|| ($data_resolution_enabled == '2' && $user_rights['data_quality_resolution'] == '3'
					&& (empty($prevUserAttr) || $prevUserAttr['response']
						|| $prevUserAttr['current_query_status'] == 'CLOSED' || $prevUserAttr['current_query_status'] == 'VERIFIED'
						|| $prevUserAttr['current_query_status'] == 'DEVERIFIED'))
			)
				// Render new form
				? self::renderFieldDataResHistoryNewForm($record, $event_id, $field, $rule_id, $instance, $prevUserAttr)
				// User does not have rights to take an action in DRW mode
				:	(($data_resolution_enabled == '2' && $prevUserAttr['current_query_status'] != 'CLOSED')
					? 	\RCView::div(array('class'=>'yellow', 'style'=>'margin:20px 0;'),
						\RCView::img(array('src'=>'exclamation_frame.png')) .
						$lang['dataqueries_213']
					)
					: \RCView::div(array('class'=>'space', 'style'=>'margin:20px 0;'), ' ')
				)
			);
		// Output html
		return $h;
	}
	// Render form to add/modify data resolution history
	public static function renderFieldDataResHistoryNewForm($record, $event_id, $field, $rule_id, $instance=1, $prevUserAttr=array())
	{
		global $lang, $data_resolution_enabled, $user_rights, $data_resolution_enabled, $field_comment_edit_delete;
		// Set background color
		$bgColor = 'background:#ddd;';
		// Put all content for last column in $td
		$td = '';
		// Determine if the rule_id contains only one field. If so, set field_name as variable.
		$ruleContainsOneField = ($field == '') ? '' : $field;
		if (is_numeric($rule_id)) {
			$dqOneField = new LockDataQuality();
			$ruleContainsOneField = $dqOneField->ruleContainsOneField($rule_id);
		}
		// Set "new comment" label
		$commentLabel = ($prevUserAttr['response_requested']) ? $lang['dataqueries_203'] : $lang['dataqueries_204'];
		## IF USER IS RE-OPENING THREAD
		if ($data_resolution_enabled == '2' && isset($prevUserAttr['current_query_status']) && $prevUserAttr['current_query_status'] == 'CLOSED')
		{
			$td .=
				// Require response from other user?
				\RCView::div(array('style'=>''),
					\RCView::checkbox(array('id'=>'dc-response_requested', 'onclick'=>"
						if ($(this).prop('checked')) {
							$('#dc-comment-div').removeClass('opacity35');$('#dc-comment').prop('disabled',false).focus();
						} else {
							$('#dc-comment-div').addClass('opacity35');$('#dc-comment').prop('disabled',true).val('');
						}")) .
					$lang['dataqueries_202']
				);
		}
		## IF USER IS CLOSING THREAD OR RETURNING BACK TO ASSIGNED USER
		elseif ($data_resolution_enabled == '2' && isset($prevUserAttr['response']) && $prevUserAttr['response'] != '')
		{
			$td .=
				// Choose thread status: close or return to user
				\RCView::div(array('style'=>''),
					\RCView::radio(array('name'=>'dc-status','id'=>'dc-response_requested-closed','value'=>'CLOSED','checked'=>'checked','onclick'=>"$('#dataResSavBtn').button('option','label','".js_escape($lang['dataqueries_151'])."');")) .
					\RCView::span(array('style'=>'color:green;font-weight:bold;'), $lang['dataqueries_151']) .
					\RCView::br() .
					\RCView::radio(array('name'=>'dc-status','id'=>'dc-response_requested','value'=>'OPEN','onclick'=>"$('#dataResSavBtn').button('option','label','".js_escape($lang['dataqueries_153'])."');")) .
					\RCView::span(array('style'=>'color:#C00000;font-weight:bold;'), $lang['dataqueries_153'])
				);
		}
		## IF USER IS OPENING A QUERY
		elseif ($data_resolution_enabled == '2' && (empty($prevUserAttr) || (!empty($prevUserAttr)
					&& ($prevUserAttr['current_query_status'] == 'DEVERIFIED' || $prevUserAttr['current_query_status'] == 'VERIFIED'
						|| !$prevUserAttr['response_requested']))))
		{
			// Get array of user_id's of users with Respond privileges
			$usersCanRespond = \User::getUsersDataResRespond();

			## Add extra radio to verify data or de-verify data
			$assignedUserSelectStyle = 'margin-bottom:5px;';
			if (empty($prevUserAttr) || (!empty($prevUserAttr) && $prevUserAttr['current_query_status'] == 'DEVERIFIED')) {
				// Option to Verify data
				$assignedUserSelectStyle = 'margin-left:24px;margin-bottom:10px;';
				$td .= 	\RCView::div(array('style'=>''),
					\RCView::radio(array('name'=>'dc-status','id'=>'dc-response_requested-verified','value'=>'VERIFIED','checked'=>'checked','onclick'=>"$('#drw_comment_optional').show();$('#dataResSavBtn').button('option','label','".js_escape($lang['dataqueries_221'])."');")) .
					\RCView::span(array('style'=>'color:green;font-weight:bold;'), $lang['dataqueries_221']) .
					\RCView::div(array('style'=>'color:gray;margin:4px 0 2px;'), '&#8212; '.$lang['global_46'].' &#8212;') .
					\RCView::radio(array('name'=>'dc-status','id'=>'dc-response_requested','value'=>'OPEN','onclick'=>"$('#drw_comment_optional').hide();$('#dataResSavBtn').button('option','label','".js_escape($lang['dataqueries_197'])."');")) .
					\RCView::span(array('style'=>'color:#C00000;font-weight:bold;'), $lang['dataqueries_197'])
				);
			} elseif ($prevUserAttr['current_query_status'] == 'VERIFIED') {
				// Option to De-Verify data
				$assignedUserSelectStyle = 'margin-left:24px;margin-bottom:10px;';
				$td .= 	\RCView::div(array('style'=>''),
					\RCView::radio(array('name'=>'dc-status','id'=>'dc-response_requested-verified','value'=>'DEVERIFIED','checked'=>'checked','onclick'=>"$('#dataResSavBtn').button('option','label','".js_escape($lang['dataqueries_224'])."');")) .
					\RCView::span(array('style'=>'color:#800000;font-weight:bold;'), $lang['dataqueries_224']) .
					\RCView::div(array('style'=>'color:gray;margin:4px 0 2px;'), '&#8212; '.$lang['global_46'].' &#8212;') .
					\RCView::radio(array('name'=>'dc-status','id'=>'dc-response_requested','value'=>'OPEN','onclick'=>"$('#dataResSavBtn').button('option','label','".js_escape($lang['dataqueries_197'])."');")) .
					\RCView::span(array('style'=>'color:#C00000;font-weight:bold;'), $lang['dataqueries_197'])
				);
			}
			// Require response from other user?
			$td .=	\RCView::div(array('style'=>$assignedUserSelectStyle),
				$lang['dataqueries_201'] . " " .
				\RCView::select(array('id'=>'dc-assigned_user_id'), $usersCanRespond, '')
			);
			if ((empty($prevUserAttr) && is_numeric($rule_id) && $ruleContainsOneField == '') || $prevUserAttr['current_query_status'] == 'VERIFIED'
				|| $prevUserAttr['current_query_status'] == 'DEVERIFIED') {
				$td .=	\RCView::div(array('class'=>'hidden'),
					\RCView::checkbox(array('id'=>'dc-response_requested', 'checked'=>'checked'))
				);
			}
			;
		}
		## IF USER IS RESPONDING TO THREAD (and has respond rights)
		elseif ($data_resolution_enabled == '2' && $prevUserAttr['response_requested'])
		{
			// Close query (optional): If user has open/close/respond rights (rather than just respond rights), then also show option to close the query
			$radioCloseOption = $radioRespondOption = $fileUploadStyle = '';
			if ($user_rights['data_quality_resolution'] == '3') {
				$radioCloseOption = \RCView::div(array('style'=>'margin:2px 0 10px;'),
					\RCView::div(array('style'=>'color:gray;margin-bottom:2px;'), '&#8212; '.$lang['global_46'].' &#8212;') .
					\RCView::radio(array('name'=>'dc-status','value'=>'CLOSED','onclick'=>"$('#dataResSavBtn').button('option','label','".js_escape($lang['dataqueries_151'])."');")) .
					\RCView::span(array('style'=>'color:green;font-weight:bold;'), $lang['dataqueries_151'])
				);
				$radioRespondOption = \RCView::radio(array('name'=>'dc-status','value'=>'OPEN','checked'=>'checked','onclick'=>"$('#dataResSavBtn').button('option','label','".js_escape($lang['dataqueries_152'])."');")) . " ";
				$fileUploadStyle = 'margin-left:24px;';
			}
			// Response drop-down
			$td .=
				\RCView::div(array('style'=>''),
					$radioRespondOption . \RCView::span(array('style'=>'color:#000066;font-weight:bold;'), $lang['dataqueries_200']) . " &nbsp;" .
					\RCView::select(array('id'=>'dc-response'), array_merge(array(''=>$lang['dataqueries_199']),
						self::getDataResolutionResponseChoices()), '')
				);
			// Upload a file (optional)
			$td .=
				\RCView::div(array('style'=>$fileUploadStyle),
					$lang['dataqueries_198'] . " &nbsp;" .
					// Span container for "Upload New Document" link
					\RCView::span(array('id'=>'drw_upload_new_container'),
						\RCView::img(array('src'=>'add.png')) .
						\RCView::a(array('href'=>'javascript:;', 'id'=>'dc-upload_doc_id', 'style'=>'color:green;text-decoration:underline',
							'onclick'=>"openDataResolutionFileUpload('".js_escape($record)."', $event_id, '$field', '$rule_id');"), $lang['form_renderer_23'])
					) .
					\RCView::div(array(),
						// Hidden link for dispaying file name of uploaded file (once uploaded)
						\RCView::a(array('href'=>'javascript:;', 'id'=>'dc-upload_doc_id-label', 'style'=>'display:none;text-decoration:underline'), '') .
						// Hidden link for removing uploaded file (once uploaded)
						\RCView::a(array('href'=>'javascript:;', 'id'=>'drw_upload_remove_doc',
							'style'=>'display:none;margin-left:10px;color:#800000;font-size:10px;', 'onclick'=>"dataResolutionDeleteUpload();"),
							'[X] '.$lang['scheduling_57']
						)
					) .
					// Hidden div to store doc_id of uploaded file
					\RCView::div(array('id'=>'drw_upload_file_container', 'class'=>'hidden'), '')
				);
			$td .= $radioCloseOption;
		}
		// Disable the comment textarea if query is closed
		$disableComments = ($data_resolution_enabled == '2' && $prevUserAttr['current_query_status'] == 'CLOSED') ? 'disabled' : '';
		$commentsDivClass = ($data_resolution_enabled == '2' && $prevUserAttr['current_query_status'] == 'CLOSED') ? 'opacity35' : '';

		// Query status label and dialog Save button text (depending on state)
		$saveBtn = $lang['dataqueries_195'];
		$commentOptionalClass = 'hidden';
		if ($data_resolution_enabled == '2')
		{
			if (empty($prevUserAttr)) {
				$saveBtn = $lang['dataqueries_221'];
				$commentOptionalClass = '';
			} else {
				if ($prevUserAttr['current_query_status'] == 'OPEN' && $prevUserAttr['response'] == '') {
					$saveBtn = $lang['dataqueries_152'];
				} elseif ($prevUserAttr['current_query_status'] == 'OPEN' && $prevUserAttr['response'] != '') {
					$saveBtn = $lang['dataqueries_151'];
				} elseif ($prevUserAttr['current_query_status'] == 'VERIFIED') {
					$saveBtn = $lang['dataqueries_224'];
				} elseif ($prevUserAttr['current_query_status'] == 'DEVERIFIED') {
					$saveBtn = $lang['dataqueries_221'];
					$commentOptionalClass = '';
				} else {
					$saveBtn = $lang['dataqueries_196'];
				}
			}
		}
		// Output Table and Save/Cancel buttons
		return 	\RCView::table(array('id'=>'newDCHistory','class'=>'form_border','cellspacing'=>'0','style'=>'table-layout:fixed;width:100%;'),
				\RCView::tr('',
					(!($data_resolution_enabled == '1' && $field_comment_edit_delete) ? '' :
						\RCView::td(array('class'=>'data', 'style'=>'border:1px solid #ccc;padding:3px 0;text-align:center;width:35px;'.$bgColor),
							''
						)
					) .
					// Invisible progress icon
					\RCView::td(array('id'=>'newDCnow','class'=>'data', 'style'=>'border:1px solid #ccc;padding:3px 8px;text-align:center;width:140px;'.$bgColor),
						\DateTimeRC::format_ts_from_ymd(NOW)
					) .
					// Username
					\RCView::td(array('class'=>'data', 'style'=>'border:1px solid #ccc;padding:3px 8px;text-align:center;width:145px;'.$bgColor),
						USERID
					) .
					\RCView::td(array('class'=>'data', 'style'=>'border:1px solid #ccc;padding:3px 8px;'.$bgColor),
						// Contents
						$td .
						// Comment box
						\RCView::div(array('id'=>'dc-comment-div','class'=>$commentsDivClass),
							($data_resolution_enabled == '2'
								? \RCView::div(array('style'=>'padding-top:5px;'),
									$lang['dataqueries_195'] .
									// Only display "optional" for comment if verifying data value
									\RCView::span(array('id'=>'drw_comment_optional','class'=>$commentOptionalClass),
										' '.$lang['survey_251']
									) .
									$lang['colon']
								)
								: ''
							) .
							\RCView::textarea(array('id'=>'dc-comment','class'=>'x-form-field notesbox',$disableComments=>$disableComments,'style'=>'height:45px;width:97%;'))
						)
					)
				)
			) .
			// SAVE & CANCEL BUTTONS
			\RCView::div(array('style'=>'padding:15px 0 7px;text-align:right;font-size:13px;font-weight:bold;vertical-align:middle;'),
				// Cancel button
				\RCView::div(array('style'=>'float:right;'),
					\RCView::button(array('class'=>'jqbutton', 'style'=>'padding: 0.4em 0.8em !important;', 'onclick'=>"$('#lock_data_resolution').dialog('close');"),
						$lang['global_53']
					)
				) .
				// Save button
				\RCView::div(array('style'=>'float:right;'),
					\RCView::button(array('id'=>'dataResSavBtn', 'class'=>'jqbutton', 'style'=>'padding: 0.4em 0.8em !important;margin-right:3px;',
						'onclick'=>"lockResolutionSave('".js_escape($field)."','".js_escape($event_id)."','".js_escape($record)."','".js_escape($rule_id)."','".js_escape($instance)."');"), $saveBtn
					)
				) .
				// "Saved!" msg
				\RCView::div(array('class'=>'hidden','id'=>'drw_saved','style'=>'padding-top:5px;color:green;margin-right:20px;float:right;'),
					\RCView::img(array('src'=>'tick.png')) .
					$lang['design_243']
				) .
				// "Saving..." msg
				\RCView::div(array('class'=>'hidden','id'=>'drw_saving','style'=>'padding-top:5px;margin-right:20px;float:right;'),
					\RCView::img(array('src'=>'progress_circle.gif')) .
					$lang['designate_forms_21']
				)
			);
	}

	private static function getDataResolutionResponseChoices($type=null)
	{
		global $lang;
		// Establish array of choices
		$choices = array(
			'DATA_MISSING' => $lang['dataqueries_161'],
			'TYPOGRAPHICAL_ERROR' => $lang['dataqueries_162'],
			'WRONG_SOURCE' => $lang['dataqueries_163'],
			'CONFIRMED_CORRECT' => $lang['dataqueries_164'],
			'OTHER' => $lang['create_project_19']
		);
		// If a $type was provided and is valid, return only its label (return string)
		if ($type != null) {
			return (isset($choices[$type]) ? $choices[$type] : '');
		}
		// Return whole array
		else {
			return $choices;
		}
	}
	// Render single section of data resolution history table
	static private function renderFieldDataResHistoryExistingSection($attr=array(), $prev_attr=array(), $num_row)
	{
		global $lang, $data_resolution_enabled, $field_comment_edit_delete, $Proj;
		// Determine if a real DRW entry or a Data History entry
		if (isset($attr['res_id'])) {
			// DRW
			// Get username of initiator
			$userInitiator = \User::getUserInfoByUiid($attr['user_id']);
			$userInitiator = ($userInitiator === false) ? \RCView::div(array('style'=>'font-size:12px;line-height:13px;color:#C00000;'), $lang['dataqueries_302']) : $userInitiator['username'];
			$cellstyle = '';
			// Get username of assigned user
			if ($num_row == '1' && isset($attr['assigned_user_id'])) {
				$userAssigned = \User::getUserInfoByUiid($attr['assigned_user_id']);
				$userAssigned = "{$userAssigned['username']} ({$userAssigned['user_firstname']} {$userAssigned['user_lastname']})";
			}
			// Get form name of this field
			$form_name = (!(isset($_POST['field_name']) && isset($Proj->metadata[$_POST['field_name']]))) ? '' : $Proj->metadata[$_POST['field_name']]['form_name'];
		} else {
			// Data History data values
			// Get username and info
			$userInitiator = $attr['user_id'];
			$cellstyle = 'background:#E2EAFA';
		}
		// Set thread status type
		$userResponded = (isset($attr['response']) && $attr['response'] != '' && !$attr['response_requested']);
		$userClosedQuery = (isset($attr['current_query_status']) && $attr['current_query_status'] == 'CLOSED');
		$userUploadedFile = (isset($attr['upload_doc_id']) && $attr['upload_doc_id'] != '');
		// Get uploaded file name and size (if applicable)
		if ($userUploadedFile) {
			$q_fileup_query = db_query("select doc_name, doc_size from redcap_edocs_metadata where doc_id = {$attr['upload_doc_id']} limit 1");
			$q_fileup = db_fetch_array($q_fileup_query);
			$q_fileup['doc_size'] = round_up($q_fileup['doc_size'] / 1024 / 1024);
			if (strlen($q_fileup['doc_name']) > 24) $q_fileup['doc_name'] = substr($q_fileup['doc_name'],0,22)."...";
			$fileup_label = "{$q_fileup['doc_name']} ({$q_fileup['doc_size']} MB)";
		}
		// Render this row or section of rows
		$h = \RCView::tr(array('id'=>'res_id-'.$attr['res_id']),
			// Edit/delete comments, if enabled
			(!($data_resolution_enabled == '1' && $field_comment_edit_delete) ? '' :
				\RCView::td(array('class'=>'data nowrap', 'style'=>'border:1px solid #ddd;padding:3px 0;width:35px;text-align:center;'.$cellstyle),
					\RCView::div(array('style'=>'margin-bottom:3px;'),
						\RCView::a(array('href'=>'javascript:;', 'onclick'=>"editFieldComment({$attr['res_id']},'$form_name',1,0);"),
							\RCView::img(array('src'=>'pencil.png', 'title'=>$lang['global_27']))
						)
					) .
					\RCView::div(array('style'=>''),
						\RCView::a(array('href'=>'javascript:;', 'onclick'=>"deleteFieldComment({$attr['res_id']},'$form_name',1);"),
							\RCView::img(array('src'=>'cross.png', 'title'=>$lang['design_170']))
						)
					)
				)
			) .
			// Date/time
			\RCView::td(array('class'=>'data nowrap', 'style'=>'border:1px solid #ddd;padding:3px 8px;text-align:center;width:140px;'.$cellstyle),
				\DateTimeRC::format_ts_from_ymd($attr['ts'])
			) .
			// Current user
			\RCView::td(array('class'=>'data', 'style'=>'border:1px solid #ddd;padding:3px 8px;text-align:center;width:145px;'.$cellstyle),
				$userInitiator
			) .
			// Comment and other attributes
			\RCView::td(array('class'=>'data', 'style'=>'border:1px solid #ddd;padding:3px 8px;'.$cellstyle),
				// If a responder responded to an opened query
				(!$userResponded ? '' :
					\RCView::div(array('style'=>''),
						\RCView::span(array('style'=>'color:#777;font-size:11px;margin-right:5px;'), $lang['dataqueries_212']) .
						\RCView::span(array('style'=>'color:#000066;'), self::getDataResolutionResponseChoices($attr['response']))
					)
				) .
				// If user uploaded a file
				(!$userUploadedFile ? '' :
					\RCView::div(array('style'=>''),
						\RCView::span(array('style'=>'color:#777;font-size:11px;margin-right:5px;'), $lang['dataqueries_211']) .
						\RCView::a(array('target'=>'_blank', 'style'=>'text-decoration:underline;', 'href'=>APP_PATH_WEBROOT."DataQuality/data_resolution_file_download.php?pid=".PROJECT_ID."&res_id={$attr['res_id']}&id={$attr['upload_doc_id']}"),
							$fileup_label
						)
					)
				) .
				// Note if user opened the query
				((!(in_array($prev_attr['current_query_status'], array('','VERIFIED','DEVERIFIED')) && $attr['current_query_status'] == 'OPEN')) ? '' :
					\RCView::div(array('style'=>''),
						\RCView::span(array('style'=>'color:#777;font-size:11px;margin-right:5px;'), $lang['dataqueries_207']) .
						\RCView::span(array('style'=>'color:#C00000;'), $lang['dataqueries_210'])
					)
				) .
				// Note if user sent query back for further attention
				((!($prev_attr['response'] != '' && $attr['response_requested'])) ? '' :
					\RCView::div(array('style'=>''),
						\RCView::span(array('style'=>'color:#777;font-size:11px;margin-right:5px;'), $lang['dataqueries_207']) .
						\RCView::span(array('style'=>'color:#C00000;'), $lang['dataqueries_209'])
					)
				) .
				// Note if user closed the query
				(!$userClosedQuery ? '' :
					\RCView::div(array('style'=>''),
						\RCView::span(array('style'=>'color:#777;font-size:11px;margin-right:5px;'), $lang['dataqueries_207']) .
						\RCView::span(array('style'=>'color:green;'), $lang['dataqueries_208'])
					)
				) .
				// Note if user re-opened the query
				((!($prev_attr['current_query_status'] == 'CLOSED' && $attr['current_query_status'] == 'OPEN')) ? '' :
					\RCView::div(array('style'=>''),
						\RCView::span(array('style'=>'color:#777;font-size:11px;margin-right:5px;'), $lang['dataqueries_207']) .
						\RCView::span(array('style'=>'color:#C00000;'), $lang['dataqueries_206'])
					)
				) .
				// Note if user verified the data
				((!($attr['current_query_status'] == 'VERIFIED')) ? '' :
					\RCView::div(array('style'=>''),
						\RCView::span(array('style'=>'color:#777;font-size:11px;margin-right:5px;'), $lang['dataqueries_207']) .
						\RCView::span(array('style'=>'color:green;'), $lang['dataqueries_221'])
					)
				) .
				// Note if the data was de-verified
				((!($attr['current_query_status'] == 'DEVERIFIED')) ? '' :
					\RCView::div(array('style'=>''),
						\RCView::span(array('style'=>'color:#777;font-size:11px;margin-right:5px;'), $lang['dataqueries_207']) .
						\RCView::span(array('style'=>'color:#800000;'),
							$lang['dataqueries_223'] .
							// If was de-verified automatically via data change, then note this
							($attr['comment'] != '' ? '' : " ".$lang['dataqueries_225'])
						)
					)
				) .
				// If was assigned to a user
				(!isset($userAssigned) ? '' :
					\RCView::div(array('style'=>''),
						\RCView::span(array('style'=>'color:#777;font-size:11px;margin-right:5px;'), $lang['dataqueries_205']) .
						\RCView::span(array('style'=>'color:#800000;'), $userAssigned)
					)
				) .
				// Display comments
				(!isset($attr['comment']) ? '' :
					\RCView::div(array('style'=>'line-height:13px;'),
						($data_resolution_enabled == '2'
							// Full DRW display
							?	\RCView::span(array('style'=>'color:#777;font-size:11px;margin-right:5px;'), $lang['dataqueries_195'].$lang['colon']) .
							"&#8220;" . nl2br(\RCView::escape($attr['comment'],false)) . "&#8221;"
							// Field Comment Log (only display the comment itself)
							: 	nl2br(\RCView::escape(filter_tags(br2nl($attr['comment'])),false))
						)
					)
				) .
				// Display data values (from Data History Widget)
				(!isset($attr['data_values']) ? '' :
					\RCView::div(array('style'=>'line-height:13px;padding-bottom:2px;'),
						\RCView::div(array('style'=>'color:#777;font-size:11px;'), $lang['data_history_03'] . $lang['colon']) .
						$attr['data_values']
					)
				) .
				// "EDITED" div that denotes if comment was edited before
				($data_resolution_enabled == '1' ?
					\RCView::div(array('class'=>'fc-comment-edit', 'style'=>($attr['field_comment_edited'] ? 'display:block;' : '')),
						$lang['dataqueries_286']
					)
					: ''
				)
			)
		);
		// Output html
		return $h;
	}
}