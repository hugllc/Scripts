<?php
/**
 *
 * PHP Version 5
 *
 * <pre>
 * HUGnetLib is a library of HUGnet code
 * Copyright (C) 2007 Hunt Utilities Group, LLC
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
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package Scripts
 * @subpackage Analysis
 * @copyright 2007 Hunt Utilities Group, LLC
 * @author Scott Price <prices@hugllc.com>
 * @version SVN: $Id: unitConversion.inc.php 369 2007-10-12 15:05:32Z prices $    
 *
 */

    function analysis_averages(&$stuff, &$device) {
        $sTime = microtime(true);
        global $verbose;
        global $endpoint;

        $day = 7 * (int)date("w");
        $unixTime = mktime(6, 0, 0, (int)date(M), $day, (int)date("Y"));

           if ($verbose > 1) print "analysis_averages start\r\n";

        $data = &$_SESSION['historyCache'];

        $average_table = $endpoint->getAverageTable($device);
        $history_table = $endpoint->getHistoryTable($device);
        $deletequery = "DELETE FROM ".$average_table
                      ." WHERE DeviceKey=".$device['DeviceKey']
                      ." AND Date=? AND Type=?";
        $dquery = $endpoint->db->Prepare($deletequery);

        $fifteen = array();
        foreach ($data as $row) {

            $time = strtotime($row["Date"]);
            if (date("i",$time) < 15) {
                $hour = date("Y-m-d H:00:00", $time);
            } else if (date("i",$time) < 30) {
                $hour = date("Y-m-d H:15:00", $time);
            } else if (date("i",$time) < 45) {
                $hour = date("Y-m-d H:30:00", $time);
            } else {
                $hour = date("Y-m-d H:45:00", $time);
            }
            $fifteen[$hour]["Count"]++;
            $fifteen[$hour]["DeviceKey"] = $device["DeviceKey"];
            $fifteen[$hour]["Date"] = $hour;
            $fifteen[$hour]["Type"] = "15MIN";
            $fifteen[$hour]["Input"] = $key;
            
            foreach ($row as $key => $val) {
                if (!is_array($val)) {
                    if (strtolower(substr($key, 0, 4)) == "data") {
                        $tKey = (int) substr($key, 4);
                        $fifteen[$hour]["Data"][$tKey] += $val;
                    }
                }
            }
        }
        $fifteenTotal = $fifteen;

        $hourly = array();
        foreach ($fifteen as $min => $row) {
            $time = strtotime($row["Date"]);
            $hour = date("Y-m-d H:00:00", $time);
            $hourly[$hour]["Count"] += $row["Count"];
            $hourly[$hour]["DeviceKey"] = $device["DeviceKey"];
            $hourly[$hour]["Date"] = $hour;
            $hourly[$hour]["Type"] = "HOURLY";
            foreach ($fifteen[$min]["Data"] as $key => $val) {
                if (!$device['doTotal'][$key]) {
                    $fifteen[$min]["Data"][$key] = $val/$row["Count"];
                }
                $hourly[$hour]["Data"][$key] += $val;
            }
        }

        $hourlyTotal = $hourly;

        $daily = array();
        $daily["Data"] = array();
        foreach ($hourly as $hour => $row) {
            $time = strtotime($row["Date"]);
            $day = date("Y-m-d 5:00:00", $time);
            $daily["Count"] += $row["Count"];
            $daily["DeviceKey"] = $device["DeviceKey"];
            $daily["Date"] = $day;
            $daily["Type"] = "DAILY";
            $colCnt = 0;
            foreach ($hourly[$hour]["Data"] as $key => $val) {
                if (!$device['doTotal'][$key]) {
                    $hourly[$hour]["Data"][$key] = $val / $row["Count"];
                }
                $daily["Data"][$key] += $val;
            }
        }

        // Set up most of the SQL query...
        $basequery = "REPLACE INTO ".$average_table." (DeviceKey,Date,Type";
        for ($i = 0; $i < $device['NumSensors']; $i++) {
            $basequery .= ",Data".$i."";
        }
        $basequery .= ") VALUES (".$device['DeviceKey'].",".$endpoint->db->Param("date").",".$endpoint->db->Param("type");
        for ($i = 0; $i < $device['NumSensors']; $i++) {
            $basequery .= ",".$endpoint->db->Param("a".$i);
        }
        $basequery .= ")";
        $avgquery = $endpoint->db->Prepare($basequery);

        if ($verbose) print " Saving Averages: ";

        // Now get the data for the query;
        $hist = array();
        $del = array();
        if ($device["MinAverage"] == "15MIN") {
            $lasterror = "";
            foreach ($fifteen as $min => $row) {
                $del[] = array($row['Date'], $row['Type']);
                $hist[] = analysis_averages_insert($row, $device['NumSensors']);
            }
            if ($verbose) print $lasterror." 15Min ";
        }


        if (($device["MinAverage"] == "15MIN") 
            || ($device["MinAverage"] == "HOURLY"))
            {
            $lasterror = "";
            foreach ($hourly as $hour => $row) {
                $del[] = array($row['Date'], $row['Type']);
                $hist[] = analysis_averages_insert($row, $device['NumSensors']);
            }
            if ($verbose) print $lasterror." Hourly ";

        }




        if (($device["MinAverage"] == "15MIN") 
            || ($device["MinAverage"] == "HOURLY")
            || ($device["MinAverage"] == "DAILY"))
            {
            // Average
            $lasterrror = "";
            foreach ($daily["Data"] as $key => $val) {
                if (!$device['doTotal'][$key]) {
                    $daily["Data"][$key] = $val / $daily["Count"];
                }
            }
            if ($verbose) print $lasterror." Daily ";


            $del[] = array($daily['Date'], $daily['Type']);
            $hist[] = analysis_averages_insert($daily, $device['NumSensors']);
//            $ret = $endpoint->db->Execute($dquery, $del);

        }

        // Get my fields for all of these queries.
        for ($i = 0; $i < $device['NumSensors']; $i++) {
            $field[$i] = ($device['doTotal'][$i]) ? " SUM(Data".$i.") " : " AVG(Data".$i.") ";
            $field[$i] .= " as Data".$i." ";
        }

        $useDate = strtotime($daily['Date']);
    
        if (($device["MinAverage"] == "15MIN") 
            || ($device["MinAverage"] == "HOURLY")
            || ($device["MinAverage"] == "DAILY")
            || ($device["MinAverage"] == "WEEKLY"))
            {

            $bquery = " FROM ".$history_table;
            $bquery .= " WHERE DeviceKey=".$device["DeviceKey"]." ";
            $d = (int)date("d", $useDate) - (int)date("w", $useDate);
            $m = (int)date("m", $useDate);
            $y = (int)date("Y", $useDate);
            $wstart = mktime(0, 0, 0, $m, $d, $y);
            $wend = mktime(23, 59, 59, $m+1, $d+7, $y);
            $bquery .= " AND (Date >= '".date("Y-m-d H:i:s", $wstart)."' ";
            $bquery .= " AND Date <= '".date("Y-m-d H:i:s", $wend)."' )";
    
            // Weekly
            $weekly = array('Date' => date("Y-m-d", $wstart), 'Type' => "WEEKLY");
            foreach ($field as $k => $f) {
                $query = "SELECT ".$f.$bquery." AND Data".$k." IS NOT null ";
                $ret = $endpoint->db->getArray($query);
                $weekly["Data"][$k] = $ret[0]["Data".$k];
            }
            $hist[] = analysis_averages_insert($weekly, $device['NumSensors']);
            if ($verbose) print $lasterror." Weekly ";
        }    

        
        if (($device["MinAverage"] == "15MIN") 
            || ($device["MinAverage"] == "HOURLY")
            || ($device["MinAverage"] == "DAILY")
            || ($device["MinAverage"] == "WEEKLY")
            || ($device["MinAverage"] == "MONTHLY"))
            {

            $bquery = " FROM ".$history_table;
            $bquery .= " WHERE DeviceKey=".$device["DeviceKey"]." ";
            $m = (int)date("m", $useDate);
            $y = (int)date("Y", $useDate);
            $mstart = mktime(0, 0, 0, $m, 1, $y);
            $mend = mktime(23, 59, 59, $m+1, 0, $y);
            $bquery .= " AND (Date >= '".date("Y-m-d H:i:s", $mstart)."' ";
            $bquery .= " AND Date <= '".date("Y-m-d H:i:s", $mend)."' )";
    
            // Monthly
            $monthly = array('Date' => date("Y-m-1", $mstart), 'Type' => "MONTHLY");
            foreach ($field as $k => $f) {
                $query = "SELECT ".$f.$bquery." AND Data".$k." IS NOT null ";
                $ret = $endpoint->db->getArray($query);
                $monthly["Data"][$k] = $ret[0]["Data".$k];
            }
            $hist[] = analysis_averages_insert($monthly, $device['NumSensors']);
            if ($verbose) print $lasterror." Monthly ";
        }    

        if (($device["MinAverage"] == "15MIN") 
            || ($device["MinAverage"] == "HOURLY")
            || ($device["MinAverage"] == "DAILY")
            || ($device["MinAverage"] == "WEEKLY")
            || ($device["MinAverage"] == "MONTHLY")
            || ($device["MinAverage"] == "YEARLY"))
            {

            $bquery = " FROM ".$history_table;
            $bquery .= " WHERE DeviceKey=".$device["DeviceKey"]." ";
            $m = (int)date("m", $useDate);
            $y = (int)date("Y", $useDate);
            $ystart = mktime(0, 0, 0, 1, 1, $y);
            $yend = mktime(23, 59, 59, 12, 31, $y);
            $bquery .= " AND (Date >= '".date("Y-m-d H:i:s", $ystart)."' ";
            $bquery .= " AND Date <= '".date("Y-m-d H:i:s", $yend)."' )";
    
            // Yearly
            $yearly = array('Date' => date("Y-1-1", $ystart), 'Type' => "YEARLY");
            foreach ($field as $k => $f) {
                $query = "SELECT ".$f.$bquery." AND Data".$k." IS NOT null ";
                $ret = $endpoint->db->getArray($query);
                $yearly["Data"][$k] = $ret[0]["Data".$k];
            }
            $hist[] = analysis_averages_insert($yearly, $device['NumSensors']);
            if ($verbose) print $lasterror." Yearly ";
        }    




        // Save all of the averages
        if ($verbose) print " Saving... ";
        $qtime = microtime(true);

        $ret = $endpoint->db->Execute($avgquery, $hist);
        if ($verbose) print " Done (".(microtime(true) - $qtime)."s)";
        if ($ret === false) {
            if ($verbose) print "Insert Failed";
            $lasterror = " Error (".$endpoint->db->MetaError()."): ".$endpoint->db->MetaErrorMsg($endpoint->db->MetaError())." ";
        }

        if ($verbose) print "\r\n$lasterror\r\n";
        $dTime = microtime(true) - $sTime;

        if ($verbose > 1) print "analysis_averages end (".$dTime."s) \r\n";
    }

    $this->register_function("analysis_averages", "Analysis");

function analysis_averages_insert($row, $count) {

    $ret[] = $row["Date"];
    $ret[] = $row["Type"];
    for ($i = 0; $i < $count; $i++) {
        // Average tables don't allow null.  This prevents nulls from getting through
        $ret[] = is_null($row['Data'][$i]) ? 0 : $row['Data'][$i];
    }
    return $ret;
}

?>
