<?php
/**
 * Tests and serializes endpoints
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
 * @subpackage Test
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2009 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */

require_once dirname(__FILE__).'/../../head.inc.php';

//$endpoint =& HUGnetDriver::getInstance($hugnet_config);

$types = array(
    "history_raw",
    "history_raw_dup",
    "history",
    "average",
    "e00391200_history",
    "e00391200_average",
    "e00391201_history",
    "e00391201_average",
    "e00392100_history",
    "e00392100_average",
    "e00392800_history",
    "e00392800_average",
);
$totalFail = 0;
$order = "ORDER BY `HistoryRawKey` DESC";
foreach ($types as $table) {
    print "Using ".$table."\n";
    $hugnet_config["table"] = $table;
    if (stristr($table, "_raw") === false) {
        $history =& HUGnetDB::getInstance("History", $hugnet_config);
    } else {
        $history =& HUGnetDB::getInstance("RawHistory", $hugnet_config);
    }
//    $history->verbose(2);
    $start = 0;
    $limit = 10000;
    while (1) {
        print "Reading Database...\n";
        $res = $history->getWhere("UTCOffset <> ?", array(0), $limit, $start, $order);
        if ((count($res) == 0) || !is_array($res)) {
            break;
        }
        $fails = 0;
        $skipped = 0;
        $done = 0;
        print "Row ".$start."\n";
        foreach ($res as $row) {
//            var_dump($row);
            if ($row["UTCOffset"] == 0) {
                $skipped ++;
                continue;
            }
            if (isset($row["HistoryRawKey"])) {
                $where = "HistoryRawKey = ".$row["HistoryRawKey"];
            } else {
                $where  = "`DeviceKey` = ".$row["DeviceKey"];
                $where .= " AND `Date` = '".$row["Date"]."'";
                if (!empty($row["Type"])) {
                    if ($Type == "DAILY") continue;
                    $where .= " AND `Type` = '".$row["Type"]."'";
            }
            }
            $ndate  = strtotime($row["Date"]);
            $ndate += 3600 * 6;
            $dst    = date("I", $row["Date"]);
            $ndate -= 3600 * $dst;
            $info   = array(
                "Date" => date("Y-m-d H:i:s", $ndate),
                "UTCOffset" => 0,
            );
//            $history->verbose(2);
            $u = $history->updateWhere($info, $where);
            if (!$u) {
                $fails ++;
//                $history->verbose(2);
//                $history->printError();
//                $history->verbose(0);
            } else {
                $done ++;
            }
        }
        print "Last Date: ".$row["Date"]." (".date("Y-m-d H:i:s", $ndate).")\n";
        if ($fails > 0) {
            print $fails ." Failed\n";
            $totalFail += $fails;
        }
        if ($skipped > 0) {
            print $skipped ." Skipped\n";
        }
        print $done." Done\n";
        $start += $limit;
    }
}

print "Total Failures: ".$totalFail."\n";

?>