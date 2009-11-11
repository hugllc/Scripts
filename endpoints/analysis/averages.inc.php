<?php
/**
 * Computes the 15 minute, hourly and daily averages
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
 * Doing the averages
 *
 * Notes on the 15 minute average:
 *
 * The ratio in this takes into account the fact that a value might
 * cross the boundry between 15 minute samples.  For instance, if we
 * are on 5 minute reads and a read happens at minute 14, we would put
 * 20% of the value into the minute 15 average, and 80% of it into the
 * minute 30 average.  We would then increment both counters by the
 * ratio of how much they got.  The first in this example would be .2,
 * the second would be .8.
 *
 * @param object &$analysis The analysis object
 * @param array  &$device   The devInfo array for the device
 *
 * @return null
 */
function Analysis_averages(&$analysis, &$device)
{
    $sTime   = microtime(true);
    $verbose = $analysis->config["verbose"];

    $day      = 7 * (int)date("w");
    $unixTime = mktime(12, 0, 0, (int)date(M), $day, (int)date("Y"));

    if ($verbose > 1) {
        print "analysis_averages start\r\n";
    }
    $data = &$analysis->historyCache;

    $average_table = $analysis->endpoint->getAverageTable($device);
    $history_table = $analysis->endpoint->getHistoryTable($device);

    $fifteen   = array();
    $arrayKeys = array();
    $insert    = array();
    $pollInt   = ($device["PollInterval"] != 0) ? $device["PollInterval"] : 1 ;

    foreach ($data as $row) {

        $time = strtotime($row["Date"]);
        if (empty($time)) {
            continue;
        }
        $min  = date("i", $time);
        if ($min < 15) {
            $hour  = date("Y-m-d H:00:00", $time);
            $hour2 = date("Y-m-d H:15:00", $time);
            $ratio = (15 - $min) / $pollInt;
        } else if ($min < 30) {
            $hour  = date("Y-m-d H:15:00", $time);
            $hour2 = date("Y-m-d H:30:00", $time);
            $ratio = (30 - $min) / $pollInt;
        } else if ($min < 45) {
            $hour  = date("Y-m-d H:30:00", $time);
            $hour2 = date("Y-m-d H:45:00", $time);
            $ratio = (45 - $min) / $pollInt;
        } else {
            $hour  = date("Y-m-d H:45:00", $time);
            $hour2 = date("Y-m-d H:00:00", ($time + 3600));
            // This removes the one record from the next day at the very end.
            if (date("d", $time) != date("d", ($time +3600))) {
                $hour2 = 0;
            }
            $ratio = (60 - $min) / $pollInt;
        }
        if ($ratio > 1) {
            $ratio = 1;
        }
        $fifteen[$hour]["Count"]    += $ratio;
        if (!empty($hour2)) {
            $fifteen[$hour2]["Count"]   += (1 - $ratio);
        }
        $fifteen[$hour]["DeviceKey"] = $device["DeviceKey"];
        $fifteen[$hour]["Date"]      = $hour;
        $fifteen[$hour]["Type"]      = "15MIN";
        $fifteen[$hour]["Input"]     = $key;

        foreach ($row as $key => $val) {
            if (!is_array($val)) {
                if (strtolower(substr($key, 0, 4)) == "data") {
                    $tKey                   = (int) substr($key, 4);
                    $fifteen[$hour][$key]  += $val * $ratio;
                    if (!empty($hour2)) {
                        $fifteen[$hour2][$key] += $val * (1- $ratio);
                    }
                    $arrayKeys[$tKey]       = $key;
                }
            }
        }
    }
    $fifteenTotal = $fifteen;

    $hourly = array();
    foreach ($fifteen as $min => $row) {
        $time = strtotime($row["Date"]);
        $hour = date("Y-m-d H:00:00", $time);
        $hourly[$hour]["Count"]    += $row["Count"];
        $hourly[$hour]["DeviceKey"] = $device["DeviceKey"];
        $hourly[$hour]["Date"]      = $hour;
        $hourly[$hour]["Type"]      = "HOURLY";
        foreach ($arrayKeys as $tKey => $key) {
            $val = $row[$key];
            if (!$device['doTotal'][$tKey]) {
                if ($row["Count"] > 0) {
                    $fifteen[$min][$key] = $val/$row["Count"];
                }
            }
            $hourly[$hour][$key] += $val;
        }
        if (analysis_averages_check_type("15MIN", $device["MinAverage"])) {
            $insert[] = $fifteen[$min];
        }
    }

    $hourlyTotal   = $hourly;
    $daily         = array();
    foreach ($hourly as $hour => $row) {
        $time = strtotime($row["Date"]);
        $day  = date("Y-m-d 12:00:00", $time);

        $daily["Count"]    += $row["Count"];
        $daily["DeviceKey"] = $device["DeviceKey"];
        $daily["Date"]      = $day;
        $daily["Type"]      = "DAILY";
        foreach ($arrayKeys as $tKey => $key) {
            $val = $row[$key];
            if (!$device['doTotal'][$tKey]) {
                if ($row["Count"] > 0) {
                    $hourly[$hour][$key] = $val / $row["Count"];
                }
            }
            $daily[$key] += $val;
        }
        if (analysis_averages_check_type("HOURLY", $device["MinAverage"])) {
            $insert[] = $hourly[$hour];
        }
    }

    if ($verbose) {
        print " Saving Averages: ";
    }
    if (analysis_averages_check_type("DAILY", $device["MinAverage"])) {
        // Average
        $lasterrror = "";
        foreach ($arrayKeys as $tKey => $key) {
            $val = $daily[$key];
            if (!$device['doTotal'][$tKey]) {
                if ($daily["Count"] > 0) {
                    $daily[$key] = $val / $daily["Count"];
                }
            }
        }
        $insert[] = $daily;

    }

    // Save all of the averages
    if ($verbose) {
        print " Saving... ";
    }
    $qtime = microtime(true);

    $analysis->average->addArray($insert, true);

    $dTime = microtime(true) - $sTime;

    if ($verbose > 1) {
        print "analysis_averages end (".$dTime."s) \r\n";
    }
}

$this->registerFunction("analysis_averages", "Analysis5");


/**
 * helps insert an average row
 *
 * @param string $type    The type setting
 * @param string $minType The minimum Type setting.
 *
 * @return array
 */
function Analysis_Averages_Check_type($type, $minType)
{
    if ($minType == "15MIN") {
        return true;
    }
    if ($minType == "HOURLY") {
        if ($type == "15MIN") {
            return false;
        } else {
            return true;
        }
    }
    if ($minType == "DAILY") {
        if (($type == "15MIN") || ($type == "HOURLY")) {
            return false;
        } else {
            return true;
        }
    }
    if ($minType == "MONTHLY") {
        if (($type == "MONTHLY") || ($type == "YEARLY")) {
            return true;
        } else {
            return false;
        }
    }
    if ($minType == "YEARLY") {
        if ($type == "YEARLY") {
            return true;
        } else {
            return false;
        }
    }
    return false;
}
?>
