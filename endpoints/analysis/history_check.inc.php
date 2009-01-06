<?php
/**
 * Checks the history to make sure that it has been crunched correctly
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
 * @param object &$analysis The analysis object
 * @param array  &$devInfo  The devInfo array for the device
 *
 * @return null
 */
function Analysis_History_check(&$analysis, &$devInfo)
{
    $sTime = microtime(true);

    $verbose = $analysis->config["verbose"];

    if ($verbose) {
        if ($verbose > 1) {
            print "analysis_history_check start\r\n";
        }
        print "\nCrunching the packets...  ";
    }
    $history =& $analysis->rawHistoryCache;
    $history =& $analysis->endpoint->InterpSensors($devInfo, $history, "GOOD");
    if ($verbose) {
        print "Done!\r\n";
    }
    $last        = false;
    $dup         = 0;
    $update      = 0;
    $bad         = 0;
    $forceUpdate = false;
    if ($analysis->deep) {
        $forceUpdate = true;
    }
    $chHist = array();
    if (is_array($history)) {
        $firstKey = null;
        foreach ($history as $key => $rec) {
            if ($firstKey === null) {
                $firstKey = $rec['HistoryRawKey'];
            }
            $lastKey = $rec['HistoryRawKey'];
            if ($verbose > 3) {
                print $lastKey;
            }
            if (($rec['Status'] == "GOOD")) {
                if ($last !== false) {
                    if ($last['DataIndex'] == $rec['DataIndex']) {
                        if ($rec['StatusOld'] != 'DUPLICATE') {
                            if ($verbose) {
                                print "Duplicate Reading ".$last['Date'];
                                print ' - '.$rec['Date'];
                                print " Index: ".$rec['DataIndex']."\r\n";
                            }
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
            // This checks to make sure the new record and the old record are
            //the same.  If they are not, it forces an update
            // We only need to see it if we are not already updating
            if (($forceUpdate === false)
                    && ($rec['SatusOld'] == 'GOOD')
                    && ($rec['Status'] == 'GOOD')) {
                foreach ($analysis->historyCache as $oldhist) {
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
                if ($verbose) {
                    print "Status Change ".$rec['Status'];
                    print " - ".$rec['StatusOld']."\r\n";
                }
                if ($rec['Status'] == 'BAD') {
                    $bad++;
                }
                $updatesql = array("Status" => $rec['Status']);
                $where     = "HistoryRawKey = ?";
                $data      = array($rec["HistoryRawKey"]);

                $ret = $analysis->rawHistory->updateWhere($update, $where, $data);

                if ($ret === false) {
                    if ($verbose) {
                        print "Update Failed\r\n";
                    }
                } else {
                    $update++;
                    if ($verbose) {
                        print "Marked ".$rec['Status'].".";
                        print " Orig Status: ".$rec['StatusOld']."\r\n";
                    }
                }
            }
            if (($rec['Status'] != $rec['StatusOld']) || $forceUpdate) {
                if ($verbose) {
                    print $rec['HistoryRawKey']." from ".$rec['Date']."\r\n ";
                }
                if ($rec['Status'] == 'GOOD') {
                    $chHist[] = $rec;
                } else {
                    $where = "`DeviceKey` = ? AND `Date` = ?";
                    $info  = array($rec['DeviceKey'], $rec['Date']);
                    $ret   = $analysis->history->removeWhere($where, $info);
                    if ($ret === false) {
                        if ($verbose) {
                            print "Remove Failed\r\n";
                        }
                    } else {
                        if ($verbose) {
                            print "Removed from history database.\r\n";
                        }
                    }
                }
            }
            if ($analysis->Deep && ($rec['Status'] != 'BAD')) {
                $chHist[] = $rec;
            }
            if ($verbose > 3) {
                print "\r\n";
            }

        }
    }

    if (count($chHist) > 0) {
        $analysis->average->addArray($chHist, true);
        $analysis->cacheHistory($devInfo);
    }
    $dTime = microtime(true) - $sTime;
    if ($verbose > 1) {
        print "analysis_history_check end (".$dTime."s)\r\n";
    }
}


$this->registerFunction("Analysis_History_check", "Analysis0");


?>
