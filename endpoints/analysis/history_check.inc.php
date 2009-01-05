<?php
/**
 *
 * PHP Version 5
 *
 * <pre>
 * Scripts related to HUGnet
 * Copyright (C) 2007-2009 Hunt Utilities Group, LLC
 * Copyright (C) 2009 Scott Price
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 3
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 * </pre>
 *
 * @category   Scripts
 * @package    Scripts
 * @subpackage Analysis
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2009 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$    
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */

/**
 * checks the history
 *
 * @param array &$stuff  Here
 * @param array &$device The devInfo array for the device
 *
 * @return null
 */
function analysis_history_check(&$stuff, &$devInfo) {
    $sTime = microtime(true);

    $verbose = $stuff->config["verbose"];
    
    if ($verbose > 1) print "analysis_history_check start\r\n";
    if ($verbose) print "\nCrunching the packets...  ";
    $history = &$stuff->rawHistoryCache;
    $history = &$stuff->endpoint->InterpSensors($devInfo, $history, "GOOD");
    if ($verbose) print "Done!\r\n";

    $last = false;
    $dup = 0;
    $update = 0;
    $bad = 0;
    $forceUpdate = false;
    if ($stuff->deep) $forceUpdate = true;

    $chHist = array();        
    if (is_array($history)) {
        $firstKey=null;
        foreach ($history as $key => $rec) {
            if ($firstKey === null) $firstKey=$rec['HistoryRawKey'];
            $lastKey = $rec['HistoryRawKey'];
            if ($verbose > 3) print $lastKey;
            if (($rec['Status'] == "GOOD")) {
                if ($last !== false) {
                    if ($last['DataIndex'] == $rec['DataIndex']) {
                        if ($rec['StatusOld'] != 'DUPLICATE') {
                            if ($verbose) print "Duplicate Reading ".$last['Date'].' - '.$rec['Date']." Index: ".$rec['DataIndex']."\r\n";
//                                $rec['StatusOld'] = $rec['Status'];
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
            if (($forceUpdate === false) && ($rec['SatusOld'] == 'GOOD') && ($rec['Status'] == 'GOOD')) {
                foreach ($stuff->historyCache as $oldhist) {
                    if ($oldhist['Date'] == $rec['Date']) {
                        if ($oldhist['DeviceKey'] == $rec['DeviceKey']) {
                            for ($i = 0; $i < $rec['ActiveSensors']; $i++) {
                                if ($oldhist['Data'.$i] != $rec['Data'.$i]) {
                                    $forceUpdate = true;
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
                $updatesql = array("Status" => $rec['Status']);
                $ret = $stuff->rawHistory->updateWhere($update, "HistoryRawKey = ?", array($rec["HistoryRawKey"]));            

                if ($ret === false) {
                    if ($verbose) print "Update Failed\r\n";
                } else {
                    $update++;
                    if ($verbose) print "Marked ".$rec['Status'].". Orig Status: ".$rec['StatusOld']."\r\n";
                }
            }
            if (($rec['Status'] != $rec['StatusOld']) || $forceUpdate) {
                if ($verbose) print $rec['HistoryRawKey']." from ".$rec['Date']."\r\n ";
                if ($rec['Status'] == 'GOOD') {
                    $chHist[] = $rec;
                } else {
                    $where = "`DeviceKey` = ? AND `Date` = ?";
                    $info = array($rec['DeviceKey'], $rec['Date']);
                    $ret = $stuff->history->removeWhere($where, $info);
                    if ($ret === false) {
                        if ($verbose) print "Remove Failed\r\n";
                    } else {
                        if ($verbose) print "Removed from history database.\r\n";
                    }
                }
            }
            if ($stuff->Deep && ($rec['Status'] != 'BAD')) {
                $chHist[] = $rec;
            }
            if ($verbose > 3) print "\r\n";

        }
    }
    
    if (count($chHist) > 0) {
        $stuff->average->addArray($chHist, true);
        $stuff->cacheHistory($devInfo);
    }
    $dTime = microtime(true) - $sTime;
    if ($verbose > 1) print "analysis_history_check end (".$dTime."s)\r\n";

}


$this->registerFunction("analysis_history_check", "Analysis0");


?>
