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
			echo "$(document).ready(function() {
			if ($('#__LOCKRECORD__-tr').length == 0) {
				$('#" . $instrument . "_complete-tr').after('<tr id=\"__LOCKRECORD__-tr\" sq_id=\"__LOCKRECORD__\"><td class=\"labelrc col-xs-7\"><label class=\"fl\" id=\"label-__LOCKRECORD__\" aria-hidden=\"true\"><div style=\"color:#A86700;\">" . $lockData[$record]['label'] . "</div></label></td><td class=\"data col-xs-5\" style=\"padding:5px;\"><div id=\"lockingts\"><b>Locked</b> <b>by " . $lockData[$record]['username'] . "</b> (" . $lockData[$record]['realname'] . ") on " . date("m/d/Y h:ia", strtotime($lockData[$record]['timestamp'])) . "</div>" . ($esigData[$record]['esign_id'] != '' ? "<div id=\"esignts\"><b>E-signed by " . $esigData[$record]['username'] . "</b> (" . $esigData[$record]['realname'] . ") on " . date("m/d/Y h:ia", strtotime($esigData[$record]['timestamp'])) . "</div>" : "") . "</td></tr>');
			}
			$('#__LOCKRECORD__-tr').css('display','table-row');
		});";
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
}