<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 3/21/2018
 * Time: 3:11 PM
 */

namespace Vanderbilt\ShowLockingLanguageExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

class ShowLockingLanguageExternalModule extends AbstractExternalModule
{
	function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id = NULL, $repeat_instance = 1) {
		global $user_rights;
		$showLock = $this->getProjectSetting('show_lock');
		if ($showLock != "1" && $user_rights['lock_record'] == "0") return;

		#Get the icons that correspond to the comment log / data resolution for the lock and esignature. Only relevent if the module is set to show the history and comment logs for these.
		$lockIcon = $this->getCommentLogIcon($project_id,$record,$event_id,'locking_data_resolution_'.$instrument,$repeat_instance);
		$esigIcon = $this->getCommentLogIcon($project_id,$record,$event_id,'esignature_data_resolution_'.$instrument,$repeat_instance);
		#Determine if the module needs to show the comment log / data resolution and locking/esingature history on the data entry forms.
		$showHistory = $this->getProjectSetting('show_history');
		#Get the lock data for the form for this record,event,instance
		$lockData = $this->getFormLockData($project_id,$instrument,$record,$event_id,$repeat_instance);

		#If a lock ID exists, this form is locked so display the information
		if ($lockData[$record]['ld_id'] != "") {
			#Get the esignature for the form for this record, event, instance
			$esigData = $this->getESignatureData($project_id, $instrument, $record, $event_id, $repeat_instance);

			#Display custom locking language if it exists, otherwise use default language
			if ($lockData[$record]['label'] == "") {
				$lockData[$record]['label'] = "Record Lock Information";
			}

			#If the element that typically displays locking information is not present, create it and place it in the usual location on the data form
			echo "<script>";
			echo $this->generateJavascriptFunctions()."
				$(document).ready(function() {
				if ($('#__LOCKRECORD__-tr').length == 0) {
					$('#" . $instrument . "_complete-tr').after('<tr id=\"__LOCKRECORD__-tr\" sq_id=\"__LOCKRECORD__\"><td class=\"labelrc col-xs-7\"><label class=\"fl\" id=\"label-__LOCKRECORD__\" aria-hidden=\"true\"><div style=\"color:#A86700;\">" . $lockData[$record]['label'] . "</div></label></td><td class=\"data col-xs-5\" style=\"padding:5px;\"><div id=\"lockingts\">".($showHistory == "1" ? "<div style=\"display:table-cell;padding-right:5px;\"><a style=\"padding-right:5px;cursor:pointer;\" href=\"javascript:;\" tabindex=\'-1\' id=\"vcc_module_lock_history\" title=\"View Locking History\" onmouseover=\"dh1(this)\" onmouseout=\"dh2(this)\"><img src=\'".APP_PATH_IMAGES."history.png\'></a><br/><a title=\"View Locking Comment Log\" id=\"vcc_module_lock_data_res\" href=\"javascript:;\" tabindex=\"-1\"><img src=\"".APP_PATH_IMAGES.$lockIcon."\" /></a></div>" : "")."<div style=\"display:table-cell;\"><b>Locked</b> <b>by " . $lockData[$record]['username'] . "</b> (" . $lockData[$record]['realname'] . ") on " . date("m/d/Y h:ia", strtotime($lockData[$record]['timestamp'])) . "</div></div>" . ($esigData[$record]['esign_id'] != '' ? "<div id=\"esignts\">".($showHistory == "1" ? "<div style=\"display:table-cell;padding-right:5px;\"><a style=\"padding-right:5px;cursor:pointer;\" href=\"javascript:;\" tabindex=\'-1\' id=\"vcc_module_esig_history\" title=\"View E-Signature History\" onmouseover=\"dh1(this)\" onmouseout=\"dh2(this)\"><img src=\'".APP_PATH_IMAGES."history.png\'></a><br/><a id=\"vcc_module_esig_data_res\" href=\"javascript:;\" title=\"View Esignature Comment Log\" tabindex=\"-1\"><img src=\"".APP_PATH_IMAGES.$esigIcon."\" /></a></div>" : "")."<div style=\"display:table-cell;\"><b>E-signed by " . $esigData[$record]['username'] . "</b> (" . $esigData[$record]['realname'] . ") on " . date("m/d/Y h:ia", strtotime($esigData[$record]['timestamp'])) . "</div></div>" : "") . "</td></tr>');
				}
				else {
					$('#__LOCKRECORD__').before('".($showHistory == "1" ? "<div style=\"display:inline-block;padding-right:5px;\"><a style=\"padding-right:5px;cursor:pointer;\" tabindex=\'-1\' id=\"vcc_module_lock_history\" title=\"View Locking History\" onmouseover=\"dh1(this)\" onmouseout=\"dh2(this)\"><img src=\'".APP_PATH_IMAGES."history.png\'></a><br/><a title=\"View Locking Comment Log\" id=\"vcc_module_lock_data_res\" href=\"javascript:;\" tabindex=\"-1\"><img src=\"".APP_PATH_IMAGES.$lockIcon."\" /></a></div>" : "")."');
					$('#__ESIGNATURE__').before('".($showHistory == "1" ? "<div style=\"display:inline-block;padding-right:5px;\"><a style=\"padding-right:5px;cursor:pointer;\" tabindex=\'-1\' id=\"vcc_module_esig_history\" title=\"View E-Signature History\" onmouseover=\"dh1(this)\" onmouseout=\"dh2(this)\"><img src=\'".APP_PATH_IMAGES."history.png\'></a><br/><a id=\"vcc_module_esig_data_res\" href=\"javascript:;\" title=\"View Esignature Comment Log\" tabindex=\"-1\"><img src=\"".APP_PATH_IMAGES.$esigIcon."\" /></a></div>" : "")."');
				}
				$(\"#vcc_module_lock_history\").click(function () {
					lockHist(\"locking\",\"$record\",$event_id,$repeat_instance,$project_id,\"$instrument\");
				});
				$(\"#vcc_module_esig_history\").click(function () {
					lockHist(\"esignatures\",\"$record\",$event_id,$repeat_instance,$project_id,\"$instrument\");
				});
				$(\"#vcc_module_lock_data_res\").click(function () {
					lockResPopup(\"locking_data_resolution\",$event_id,\"$record\",null,null,$repeat_instance,$project_id,\"$instrument\");
				});
				$(\"#vcc_module_esig_data_res\").click(function () {
					lockResPopup(\"esignature_data_resolution\",$event_id,\"$record\",null,null,$repeat_instance,$project_id,\"$instrument\");
				});
				
				$('#__LOCKRECORD__-tr').css('display','table-row');";
			echo "});";
			echo "</script>";
		}
		// If the form is not locked but we want to see the locking history, need to add them to the existing lock/unlock div
		elseif ($showHistory == "1") {
			echo "<script>";
				echo $this->generateJavascriptFunctions()."
					$(document).ready(function() {
					$('#__LOCKRECORD__').before('<div style=\"display:inline-block;padding-right:5px;\"><a style=\"padding-right:8px;cursor:pointer;\" tabindex=\'-1\' id=\"vcc_module_lock_history\" title=\"View Locking History\" onmouseover=\"dh1(this)\" onmouseout=\"dh2(this)\"><img src=\'".APP_PATH_IMAGES."history.png\'></a><br/><a title=\"View Locking Comment Log\" id=\"vcc_module_lock_data_res\" tabindex=\"-1\"><img src=\"".APP_PATH_IMAGES.$lockIcon."\" /></a></div>');
					$('#esignchk').prepend('<div style=\"display:inline-block;padding-right:5px;\"><a style=\"padding-right:5px;cursor:pointer;\" tabindex=\'-1\' id=\"vcc_module_esig_history\" title=\"View E-Signature History\" onmouseover=\"dh1(this)\" onmouseout=\"dh2(this)\"><img src=\'".APP_PATH_IMAGES."history.png\'></a><br/><a id=\"vcc_module_esig_data_res\" title=\"View Esignature Comment Log\" tabindex=\"-1\"><img src=\"".APP_PATH_IMAGES.$esigIcon."\" /></a></div>');
				
					$(\"#vcc_module_lock_history\").click(function () {
						lockHist(\"locking\",\"$record\",$event_id,$repeat_instance,$project_id,\"$instrument\");
					});
					$(\"#vcc_module_esig_history\").click(function () {
						lockHist(\"esignatures\",\"$record\",$event_id,$repeat_instance,$project_id,\"$instrument\");
					});
					
					$(\"#vcc_module_lock_data_res\").click(function () {
						lockResPopup(\"locking_data_resolution\",$event_id,\"$record\",null,null,$repeat_instance,$project_id,\"$instrument\");
					});
					$(\"#vcc_module_esig_data_res\").click(function () {
						lockResPopup(\"esignature_data_resolution\",$event_id,\"$record\",null,null,$repeat_instance,$project_id,\"$instrument\");
					});
					
					$('#__LOCKRECORD__-tr').css('display','table-row');";
				echo "});";
			echo "</script>";
		}
	}

	/*
	 * Finds locking information for the form for a specific record, event, and instance.
	 * @param $projectID The project being used.
	 * @param $form The data collection instrument to check lock status of.
	 * @param $recordID Record ID of the record that is locked.
	 * @param $eventID Event ID of the form that is locked.
	 * @param $instance Instance ID of the form that is locked.
	 * @return Array with keys of record ID, and elements of ld_id ('Lock ID'), timestamp (when locking happened), label (custom label for locking, if present), username (User Name of person who performed lock), realName (Real name of the user who locked)
	 */
	function getFormLockData($projectID, $form, $recordID, $eventID, $instance) {
		$recordLockData = array();
		$sql = "SELECT d.ld_id,d.username,d.timestamp,d2.label,CONCAT(d3.user_firstname,' ',d3.user_lastname) as realname
		FROM redcap_locking_data d
		LEFT JOIN redcap_locking_labels d2
			ON d.project_id=d2.project_id AND d.form_name=d2.form_name
		LEFT JOIN redcap_user_information d3
			ON d.username=d3.username
		WHERE d.project_id=" . $projectID. "
		AND d.form_name='" . $form. "'
		AND d.event_id='".$eventID."'
		AND d.instance='".$instance."'
		AND d.record=" . $recordID;
		//echo "$sql<br/>";
		//echo "alert(\"".$sql."\")";
		$result = db_query($sql);
		while ($row = db_fetch_assoc($result)) {
			$recordLockData[$recordID] = $row;
		}
		return $recordLockData;
	}

	/*
	 * Finds e-signature information for the form for a specific record, event, and instance.
	 * @param $projectID The project being used.
	 * @param $form The data collection instrument to check lock status of.
	 * @param $recordID Record ID of the record that is locked.
	 * @param $eventID Event ID of the form that is locked.
	 * @param $instance Instance ID of the form that is locked.
	 * @return Array with keys of record ID, and elements of esign_id ('Lock ID'), timestamp (when locking happened), label (custom label for locking, if present), username (User Name of person who performed lock), realName (Real name of the user who locked)
	 */
	function getESignatureData($projectID, $form, $recordID, $eventID, $instance) {
		$recordSignData = array();
		$sql = "SELECT d.esign_id,d.username,d.timestamp,CONCAT(d2.user_firstname,' ',d2.user_lastname) as realname
		FROM redcap_esignatures d
		LEFT JOIN redcap_user_information d2
			ON d.username=d2.username
		WHERE d.project_id=" . $projectID. "
		AND d.form_name='" . $form. "'
		AND d.event_id='".$eventID."'
		AND d.instance='".$instance."'
		AND d.record=" . $recordID;
		//echo "$sql<br/>";
		//echo "alert(\"".$sql."\")";
		$result = db_query($sql);
		while ($row = db_fetch_assoc($result)) {
			$recordSignData[$recordID] = $row;
		}
		return $recordSignData;
	}

	/*
	 * Function to generate javascript functions on the data entry form that generate/show the comment log/data resolution popup and the lock/esignature history popup. These are modified versions of the one for base REDCap, done to get around the lock/esignature fields not being actual fields in the project metadata. Makes calls to our custom code to create the actual popups.
	 * @return String that contains the lockHist, lockResPopup, and lockResolutionSave javascript functions.
	 * */
	function generateJavascriptFunctions() {
		return "function lockHist(type,record,event_id,instance,pid,instrument) {
						// Get window scroll position before we load dialog content
						var windowScrollTop = $(window).scrollTop();
						if (record == null) record = decodeURIComponent(getParameterByName('id'));
						if ($('#data_history').hasClass('ui-dialog-content')) $('#data_history').dialog('destroy');
						$('#dh_var').html(type);
						$('#data_history2').html('<p><img src=\"'+app_path_images+'progress_circle.gif\"> Loading...</p>');
						$('#data_history').dialog({ bgiframe: true, title: 'History of '+type+' for record \"'+record+'\"', modal: true, width: 650, zIndex: 3999, buttons: {
							Close: function() { $(this).dialog('destroy'); } }
						});
						$.post(\"".$this->getUrl('lock_history_popup.php')."\", {type: type, event_id: event_id, record: record, instance: instance, pid: pid, instrument: instrument }, function(data){
							$('#data_history2').html(data);
							// Adjust table height within the dialog to fit
							var tableHeightMax = 300;
							if ($('#data_history3').height() > tableHeightMax) {
								$('#data_history3').height(tableHeightMax);
								$('#data_history3').scrollTop( $('#data_history3')[0].scrollHeight );
								// Reset window scroll position, if got moved when dialog content was loaded
								$(window).scrollTop(windowScrollTop);
								// Re-center dialog
								$('#data_history').dialog('option', 'position', { my: \"center\", at: \"center\", of: window });
							}
							// Highlight the last row in DH table
							if ($('table#dh_table tr').length > 1) {
								setTimeout(function(){
									highlightTableRowOb($('table#dh_table tr:last'), 3500);
								},300);
							}
						});
					}
				function lockResPopup(field,event_id,record,existing_record,rule_id,instance,pid,instrument) {
						if (typeof instance == \"undefined\") instance = 1;
						if (record == null) record = getParameterByName('id');
						if (existing_record == null) existing_record = $('form#form :input[name=\"hidden_edit_flag\"]').val();
						if (rule_id == null) rule_id = '';
						// Hide floating field tooltip on form (if visible)
						$('#tooltipDRWsave').hide();
						showProgress(1,0);
						// Get dialog content via ajax
					
						$.post(\"".$this->getUrl('lock_comment_log_popup.php')."\", { rule_id: rule_id, action: 'view', field_name: field, event_id: event_id, record: record, existing_record: existing_record, instance: instance, pid: pid, instrument: instrument }, function(data){
							showProgress(0,0);
							// Parse JSON
							var json_data = jQuery.parseJSON(data);
							if (existing_record == 1) {
								// Get window scroll position before we load dialog content
								var windowScrollTop = $(window).scrollTop();
								// Load the dialog content
								initDialog('lock_data_resolution');
								$('#lock_data_resolution').html(json_data.content);
								initWidgets();
								// Set dialog width
								var dialog_width = (data_resolution_enabled == '1') ? 700 : 750;
								// Open dialog
								$('#lock_data_resolution').dialog({ bgiframe: true, title: json_data.title, modal: true, width: dialog_width, zIndex: 3999, destroy: 'fade' });
								// Adjust table height within the dialog to fit
								var existingRowsHeightMax = 300;
								if ($('#existingDCHistoryDiv').height() > existingRowsHeightMax) {
									$('#existingDCHistoryDiv').height(existingRowsHeightMax);
									$('#existingDCHistoryDiv').scrollTop( $('#existingDCHistoryDiv')[0].scrollHeight );
									// Reset window scroll position, if got moved when dialog content was loaded
									$(window).scrollTop(windowScrollTop);
									// Re-center dialog
									$('#lock_data_resolution').dialog('option', 'position', { my: \"center\", at: \"center\", of: window });
								}
								// Put cursor inside text box
								$('#dc-comment').focus();
							} else {
								// If record does not exist yet, then give warning that will not work
								initDialog('lock_data_resolution');
								$('#lock_data_resolution').css('background-color','#FFF7D2').html(json_data.content);
								initWidgets();
								$('#lock_data_resolution').dialog({ bgiframe: true, title: json_data.title, modal: true, width: 500, zIndex: 3999 });
							}
						});
					}
					function lockResolutionSave(field,event_id,record,rule_id,instance) {
						if (typeof instance == \"undefined\") instance = 1;
						// Set vars
						if (record == null) record = getParameterByName('id');
						if (rule_id == null) rule_id = '';
						// Check input values
						var comment = trim($('#dc-comment').val());
						//alert( $('#lock_data_resolution input[name=\"dc-status\"]:checked').val() );return;
						if (comment.length == 0 && ($('#lock_data_resolution input[name=\"dc-status\"]').length == 0
							|| ($('#lock_data_resolution input[name=\"dc-status\"]').length && $('#lock_data_resolution input[name=\"dc-status\"]:checked').val() != 'VERIFIED'))) {
							simpleDialog(\"A comment is required. Please enter a comment.\",\"ERROR: Enter comment\");
							return;
						}
						var query_status = ($('#lock_data_resolution input[name=\"dc-status\"]:checked').length ? $('#lock_data_resolution input[name=\"dc-status\"]:checked').val() : '');
						if ($('#dc-response').length && query_status != 'CLOSED' && $('#dc-response').val().length == 0) {
							simpleDialog(\"A response is required. Please select a response option from the drop-down.\",\"ERROR: Select response option\");
							return;
						}
						var response = (($('#dc-response').length && query_status != 'CLOSED') ? $('#dc-response').val() : '');
						// Note if user is sending query back for further attention (rather than closing it)
						var send_back = (query_status != 'CLOSED' && $('#dc-response_requested-closed').length) ? 1 : 0;
						// Determine if we're re-opening the query (i.e. if #dc-response_requested is a checkbox and assign user drop-down is not there)
						var reopen_query = ($('#dc-response_requested').length && $('#dc-response_requested').attr('type') == 'checkbox' && $('#dc-assigned_user_id').length == 0) ? 1 : 0;
						// If user is responding to query, check for file uploaded
						var upload_doc_id = '';
						var delete_doc_id = '';
						delete_doc_id_count = 0;
						if ($('#drw_upload_file_container input.drw_upload_doc_id').length > 0) {
							// Loop through all doc_id's available
							delete_doc_id = new Array();
							$('#drw_upload_file_container input.drw_upload_doc_id').each(function(){
								if ($(this).attr('delete') == 'yes') {
									delete_doc_id[delete_doc_id_count++] = $(this).val();
								} else {
									upload_doc_id = $(this).val();
								}
							});
							delete_doc_id = delete_doc_id.join(\",\");
						}
						// Disable all input fields in pop-up while saving
						$('#newDCHistory :input').prop('disabled',true);
						$('#lock_data_resolution .jqbutton').button('disable');
						// Display saving icon
						$('#drw_saving').removeClass('hidden');
						// Get start time before ajax call is made
						var starttime = new Date().getTime();
						// Make ajax call
						$.post(\"".$this->getUrl('lock_comment_log_popup.php')."\", { action: 'save', field_name: field, event_id: event_id, record: record,
							comment: comment,
							response_requested: (($('#dc-response_requested').length && $('#dc-response_requested').prop('checked')) ? 1 : 0),
							upload_doc_id: upload_doc_id, delete_doc_id: delete_doc_id,
							assigned_user_id: (($('#dc-assigned_user_id').length) ? $('#dc-assigned_user_id').val() : ''),
							status: query_status, send_back: send_back,
							response: response, reopen_query: reopen_query,
							rule_id: rule_id,
							instance: instance
						}, function(data){
							if (data=='0') {
								alert(woops);
							} else {
								// Parse JSON
								var json_data = jQuery.parseJSON(data);
								// Update new timestamp for saved row (in case different)
								$('#newDCnow').html(json_data.tsNow);
								// Display saved icon
								$('#drw_saving').addClass('hidden');
								$('#drw_saved').removeClass('hidden');
								// Set bg color of last row to green
								$('table#newDCHistory tr td.data').css({'background-color':'#C1FFC1'});
								// Page-dependent actions
								if (page == 'DataQuality/field_comment_log.php') {
									// Field Comment Log page: reload table
									reloadFieldCommentLog();
								} else if (page == 'DataQuality/resolve.php') {
									// Data Quality Resolve Issues page: reload table
									dataResLogReload();
								} else if (page == 'DataQuality/index.php') {
									// Update count in tab badge
									$('#dq_tab_issue_count').html(json_data.num_issues);
								}
								// Update icons/counts
								if (page == 'DataEntry/index.php' || page == 'DataQuality/index.php') {
									// Data Quality Find Issues page: Change ballon icon for this field/rule result
									$('#dc-icon-'+rule_id+'_'+field+'__'+record).attr('src', json_data.icon);
									// Update number of comments for this field/rule result
									$('#dc-numcom-'+rule_id+'_'+field+'__'+record).html(json_data.num_comments);
									// Data Entry page: Change ballon icon for field
									$('#dc-icon-'+field).attr('src', json_data.icon).attr('onmouseover', '').attr('onmouseout', '');
								}
								// CLOSE DIALOG: Get response time of ajax call (to ensure closing time is always the same even with longer requests)
								var endtime = new Date().getTime() - starttime;
								var delaytime = 1500;
								var timeouttime = (endtime >= delaytime) ? 1000 : (delaytime - endtime);
								setTimeout(function(){
									// Close dialog with fade effect
									$('#lock_data_resolution').dialog('option', 'hide', {effect:'fade', duration: 500}).dialog('close');
									// Highlight table row in form (to emphasize where user was) - Data Entry page only
									if (page == 'DataEntry/index.php') {
										setTimeout(function(){
											highlightTableRow(field+'-tr',3000);
										},200);
									}
									// Destroy the dialog so that fade effect doesn't persist if reopened
									setTimeout(function(){
										if ($('#lock_data_resolution').hasClass('ui-dialog-content')) $('#lock_data_resolution').dialog('destroy');
									},500);
								}, timeouttime);
							}
						});
					}";
	}

	/*
	 * This mimics the REDCap functionality of determining what icon needs to be used for a certain status in the data resolution workflow. Required because these fields aren't in the project, and to extremely streamline the process of determining the icon.
	 * @param $projectID The project being used.
	 * @param $record Record ID of the record being examined.
	 * @param $eventID Event ID of the form.
	 * @param $fieldName Field name to check status of. Will either be 'locking_data_resolution' or 'esignature_data_resolution'
	 * @param $instance Instance ID of the form.
	 * @return Filename of the icon image to use for the status. Does not include the path to the image.
	 */
	function getCommentLogIcon($projectID, $record, $eventID, $fieldName, $instance = '1') {
		// This is used in comment logs or any data resolution that hasn't been started yet.
		$icon = "balloon_left_bw2.gif";
		$sql = "SELECT status_id, query_status
			FROM redcap_data_quality_status
			WHERE project_id=$projectID
			AND record='$record'
			AND event_id=$eventID
			AND instance=$instance
			AND field_name='$fieldName'";
		$result = db_query($sql);
		if ($result->num_rows > 0) {
			$currentStatusID = "";
			$currentStatus = "";
			while ($row = db_fetch_assoc($result)) {
				$currentStatus = $row['query_status'];
				$currentStatusID = $row['status_id'];
			}
			switch ($currentStatus) {
				case "OPEN":
					// The icon used for an open data resolution depends on whether there have been responses to it
					$openSql = "SELECT response
						FROM redcap_data_quality_resolutions
						WHERE status_id = $currentStatusID
						AND response IS NOT NULL
						ORDER BY ts DESC LIMIT 1";
					$openResult = db_query($openSql);
					if ($openResult->num_rows > 0) {
						$icon = "balloon_exclamation_blue.gif";
					}
					else {
						$icon = "balloon_exclamation.gif";
					}
					break;
				case "CLOSED":
					$icon = "balloon_tick.gif";
					break;
				case "VERIFIED":
					$icon = "tick_circle.png";
					break;
				case "DEVERIFIED":
					$icon = "exclamation_red.png";
					break;
				default:
					$icon = "balloon_left.png";
			}
		}

		return $icon;
	}
}