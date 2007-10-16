<?php
/**
 *   <pre>
 *   HUGnetLib is a library of HUGnet code
 *   Copyright (C) 2007 Hunt Utilities Group, LLC
 *   
 *   This program is free software; you can redistribute it and/or
 *   modify it under the terms of the GNU General Public License
 *   as published by the Free Software Foundation; either version 3
 *   of the License, or (at your option) any later version.
 *   
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *   
 *   You should have received a copy of the GNU General Public License
 *   along with this program; if not, write to the Free Software
 *   Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *   </pre>
 *
 *   @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *   @package Scripts
 *   @subpackage Analysis
 *   @copyright 2007 Hunt Utilities Group, LLC
 *   @author Scott Price <prices@hugllc.com>
 *   @version $Id: unitConversion.inc.php 369 2007-10-12 15:05:32Z prices $    
 *
 */

	function analysis_history_check(&$stuff) {
        $sTime = microtime(TRUE);

		global $endpoint;
		global $rwhistory;
		global $prefs;
        global $verbose;
        $device = &$_SESSION['devInfo'];

		if ($verbose > 1) print "analysis_history_check start\r\n";
		if ($verbose) print "Crunching the packets...  ";
        $history = &$_SESSION['rawHistoryCache'];
		$history = $endpoint->InterpSensors($_SESSION['devInfo'], $history);
		if ($verbose) print "Done!\r\n";

        $history_table = $endpoint->getHistoryTable($_SESSION['devInfo']);

        $device = &$_SESSION['devInfo'];
		
		$last = FALSE;
		$dup = 0;
		$update = 0;
		$bad = 0;
        $forceUpdate = FALSE;
        if ($_SESSION['Deep']) $forceUpdate = TRUE;


        $chHistt = array();		
		if (is_array($history)) {
			$firstKey=NULL;
			foreach($history as $key => $rec) {
				if ($firstKey === NULL) $firstKey=$rec['HistoryRawKey'];
				$lastKey = $rec['HistoryRawKey'];
                if ($verbose > 3) print $lastKey;
				if (($rec['Status'] == "GOOD")) {
					if ($last !== FALSE) {
						if ($last['DataIndex'] == $rec['DataIndex']) {
							if ($rec['StatusOld'] != 'DUPLICATE') {
								if ($verbose) print "Duplicate Reading ".$last['Date'].' - '.$rec['Date']." Index: ".$rec['DataIndex']."\r\n";
//								$rec['StatusOld'] = $rec['Status'];
								$dup++;
							}
							$history[$key]['Status'] = 'DUPLICATE';
						} else {
							$last = $rec;						
						}
					} else {
						$last = $rec;
					}
				} else {
				}
				// This checks to make sure the new record and the old record are the same.
				// If they are not, it forces an update
				// We only need to see it if we are not already updating
                if (($forceUpdate === FALSE) && ($rec['SatusOld'] == 'GOOD') && ($rec['Status'] == 'GOOD')) {
                    foreach($_SESSION['historyCache'] as $oldhist) {
                        if ($oldhist['Date'] == $rec['Date']) {
                            if ($oldhist['DeviceKey'] == $rec['DeviceKey']) {
                                for($i = 0; $i < $rec['ActiveSensors']; $i++) {
                                    if ($oldhist['Data'.$i] != $rec['Data'.$i]) {
                                        $forceUpdate = TRUE;
                                        break;
                                    }
                                }
                                break;
                            }
                        }
                    }
                }
				if (($rec['Status'] != $rec['StatusOld'])) {
					if ($verbose) print "Status Change ".$rec['Status']." - ".$rec['StatusOld']."\r\n";
					if ($rec['Status'] == 'BAD') $bad++;
//					$rwhistory->reset();
//					$rwhistory->setWhere('HistoryRawKey='.$rec['HistoryRawKey']);
//					$ret = $rwhistory->update(array('Status' => $rec['Status']));
                    $updatesql = array("Status" => $rec['Status']);
                    $ret  = $endpoint->db->AutoExecute('history_raw', $updatesql, 'UPDATE', 'HistoryRawKey='.$rec['HistoryRawKey']);

					if ($ret === FALSE) {
						if ($verbose) print "Update Failed\r\n";
					} else {
						$update++;
						if ($verbose) print "Marked ".$rec['Status'].". Orig Status: ".$rec['StatusOld']."\r\n";
					}
                }
				if (($rec['Status'] != $rec['StatusOld']) || $forceUpdate) {
                    if ($verbose) print $rec['HistoryRawKey']." from ".$rec['Date']." ";
					if ($rec['Status'] == 'GOOD') {
                        $chHist[] = history_check_insert($rec, $device['NumSensors']);
/*
                        $ret  = $endpoint->db->AutoExecute($history_table, $rec, 'INSERT');
//var_dump($ret);
                        if ($ret == FALSE) {
                            $ret  = $endpoint->db->AutoExecute($history_table, $rec, 'UPDATE', "Date='".$rec['Date']."' AND DeviceKey=".$rec['DeviceKey']);

    						if ($ret === FALSE) {
    							if ($verbose) print "Update Failed\r\n";
    						} else {
    							if ($verbose) print "Updated the History\r\n";
    						}							
    				    } else {
   							if ($verbose) print "Added the History\r\n";    				    
    				    }
*/
					} else {
						$info = array(
							'DeviceKey' => $rec['DeviceKey'],
							'Date' => $rec['Date'],
						);
						$ret = $endpoint->db->Execute("DELETE FROM ".$history_table." WHERE ".
						                              " DeviceKey = ".$rec['DeviceKey'].
						                              " AND ".
						                              " Date='".$rec['Date']."'"
						                              );
						if ($ret === FALSE) {
							if ($verbose) print "Remove Failed\r\n";
						} else {
							if ($verbose) print "Removed from history database.\r\n";
						}
					}
				}
				if ($_SESSION['Deep'] && ($rec['Status'] != 'BAD')) {
                    $chHist[] = history_check_insert($rec, $device['NumSensors']);
/*                    $ret  = $endpoint->db->AutoExecute($history_table, $rec, 'UPDATE', 'DeviceKey='.$rec['DeviceKey']." AND Date='".$rec['Date']."'");

					if ($ret === FALSE) {
						if ($verbose) print " Update Failed ";
					} else {
                        if ($verbose > 3) print " Updated ";
					}							
*/
				}
                if ($verbose > 3) print "\r\n";

			}
		}
		$_SESSION['bad'] += $bad;
		$_SESSION['dup'] += $dup;
		$_SESSION['update'] += $update;
		if ($verbose) print "Found ".$_SESSION['dup']." Duplicates, ".$_SESSION['bad']." Bad and updated ".$_SESSION['update']." Records\r\n";
		if ($verbose) print "Keys from ".$firstKey." to ".$lastKey."\r\n";

        if ($_SESSION['Deep']) {
            $_SESSION['historyCache'] = $history;
        }
        // Set up most of the SQL query...
        $basequery = "REPLACE INTO ".$history_table." (DeviceKey,Date,deltaT";
        for($i = 0; $i < $device['NumSensors']; $i++) {
            $basequery .= ",Data".$i."";
        }
        $basequery .= ") VALUES (".$device['DeviceKey'].",".$endpoint->db->Param("date").",".$endpoint->db->Param("deltaT");
        for($i = 0; $i < $device['NumSensors']; $i++) {
            $basequery .= ",".$endpoint->db->Param("a".$i);
        }
        $basequery .= ")";
        $basequery = $endpoint->db->Prepare($basequery);
            
        $ret = $endpoint->db->Execute($basequery, $chHist);


        $dTime = microtime(TRUE) - $sTime;
		if ($verbose > 1) print "analysis_history_check end (".$dTime."s)\r\n";


	}


	$this->register_function("analysis_history_check", "Analysis0");

function history_check_insert($row, $count) {

    $ret[] = $row["Date"];
    $ret[] = $row["deltaT"];
    for($i = 0; $i < $count; $i++) {
        $ret[] = $row['Data'.$i];
    }
    return $ret;
}

?>
