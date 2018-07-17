<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 5/17/2018
 * Time: 1:29 PM
 */
###############################################################################################################
# This file is a modified version of the file in REDCap that handles a field's history popups. This page is to
# get around the REDCap file performing checks to make sure you're trying to get the history for a field on the
# REDCap project, and the locking/esignature values don't count. We can simply re-use the same popup REDCap does
# since we're only displaying history and don't need to change any defaults REDCap sets up for the popup.
###############################################################################################################

global $lang, $require_change_reason;
// Do URL decode of name (because it original was fetched from query string before sent via Post)
//$record = urldecode($record);
$project_id = $_POST['pid'];
$record = db_escape($_POST['record']);
$event_id = $_POST['event_id'];
$lockType = $_POST['type'];
$lockModuleLang = parse_ini_file("lock_module_language.ini");
// Set $instance
$instance = is_numeric($_POST['instance']) ? (int)$_POST['instance'] : 1;
// Get data history log
$time_value_array = getDataHistoryLog($project_id, $record, $event_id, $lockType, $instance);
// Get highest array key
$max_dh_key = count($time_value_array)-1;
// Loop through all rows and add to $rows
foreach ($time_value_array as $key=>$row)
{
	$rows .= RCView::tr(array('id'=>($max_dh_key == $key ? 'dh_table_last_tr' : '')),
		RCView::td(array('class'=>'data', 'style'=>'padding:5px 8px;text-align:center;width:150px;'),
			DateTimeRC::format_ts_from_ymd($row['ts']) .
			// Display "lastest change" label for the last row
			($max_dh_key == $key ? RCView::div(array('style'=>'color:#C00000;font-size:11px;padding-top:5px;'), $lang['dataqueries_277']) : '')
		) .
		RCView::td(array('class'=>'data', 'style'=>'border:1px solid #ddd;padding:3px 8px;text-align:center;width:100px;word-wrap:break-word;'),
			$row['user']
		) .
		RCView::td(array('class'=>'data', 'style'=>'border:1px solid #ddd;padding:3px 8px;text-align:center;'),
			$row['description']
		)
	);
}
// If no data history log exists yet for field, give message
if (empty($time_value_array))
{
	$rows .= RCView::tr('',
		RCView::td(array('class'=>'data', 'colspan'=>($require_change_reason ? '4' : '3'), 'style'=>'border-top: 1px #ccc;padding:6px 8px;text-align:center;'),
			$lang['data_history_05']
		)
	);
}
// Output the table headers as a separate table (so they are visible when scrolling)
$table = RCView::table(array('class'=>'form_border', 'style'=>'table-layout:fixed;border:1px solid #ddd;width:97%;'),
	RCView::tr('',
		RCView::td(array('class'=>'label_header', 'style'=>'padding:5px 8px;width:150px;'),
			$lang['data_history_01']
		) .
		RCView::td(array('class'=>'label_header', 'style'=>'padding:5px 8px;width:100px;'),
			$lang['global_17']
		) .
		RCView::td(array('class'=>'label_header', 'style'=>'padding:5px 8px;'),
			"Type of Change"
		)
	)
);
// Output table html
$table .= RCView::div(array('id'=>'data_history3', 'style'=>'overflow:auto;'),
	RCView::table(array('id'=>'dh_table', 'class'=>'form_border', 'style'=>'table-layout:fixed;border:1px solid #ddd;width:97%;'),
		$rows
	)
);

// Return html
echo $table;

// Retrieve all logging that has been done in regards to the $type of 'lock' or 'esignature'
function getDataHistoryLog($project_id, $record, $event_id, $type, $instance=1)
{
	global $double_data_entry, $user_rights, $longitudinal;

	$field_type = ($type == "esignatures" ? "ESIGNATURE" : "LOCK_RECORD");

	// Adjust record name for DDE
	if ($double_data_entry && isset($user_rights) && $user_rights['double_data'] != 0) {
		$record .= "--" . $user_rights['double_data'];
	}

	// Retrieve history and parse field data values to obtain value for specific field
	$sql = "SELECT log_event_id, project_id, timestamp(ts) as ts, user, event, sql_log, pk, event_id, description
			FROM redcap_log_event
			WHERE project_id=$project_id
			AND pk = '$record'
			AND (event_id = $event_id OR event_id IS NULL)
			AND event IN ('$field_type','DELETE')
			ORDER BY log_event_id";
	//echo "$sql<br/>";
	$q = db_query($sql);
	// Loop through each row from log_event table. Each will become a row in the new table displayed.
	while ($row = db_fetch_assoc($q)) {
		// If the record was deleted in the past, then remove all activity before that point
		if ($row['event'] == 'DELETE') {
			$time_value_array = array();
			continue;
		}
		// Flag to denote if found match in this row
		$matchedThisRow = false;
		// Get timestamp
		$ts = $row['ts'];
		// Get username
		$user = $row['user'];
		// Get type of log event
		$logEvent = $row['event'];
		// Get the sql log of the event
		$sqlLog = $row['sql_log'];
		// Get the log event description
		$description = $row['description'];
		// Need to try to parse the instance of the log event to match the instance we're looking for
		if ($description == "Unlock record" || $description == "Negate e-signature") {
			if (strpos($sqlLog,"instance = '$instance'") !== false) {
				$matchedThisRow = true;
			}
		}
		else {
			preg_match_all('/\((.*?)\)/',preg_replace("/(\r?\n?\t)/", "",$sqlLog),$outputSql);
			$columnsSplit = explode(",",$outputSql[1][0]);
			$valueSplit = explode(",",$outputSql[1][1]);
			$instanceIndex = "";
			foreach ($columnsSplit as $index => $columnName) {
				if (strpos($columnName,"instance") !== false) {
					$instanceIndex = $index;
				}
			}
			$preparedSqlInstance = str_replace(array("'"," "),"",$valueSplit[$instanceIndex]);
			if ((int)$preparedSqlInstance === (int)$instance) {
				$matchedThisRow = true;
			}
		}

		// Add to array (if match was found in this row)
		if ($matchedThisRow) {
			// Set array key as timestamp + extra digits for padding for simultaneous events
			$key = strtotime($ts) * 100;
			// Ensure that we don't overwrite existing logged events
			while (isset($time_value_array[$key . ""])) $key++;
			// Add to array
			$time_value_array[$key . ""] = array('ts' => $ts, 'user' => $user, 'description' => $row['description']);
		}
	}
	// Sort by timestamp
	ksort($time_value_array);
	// Return data history log
	return $time_value_array;
}