<?php
/**
 * Checks for the last sensor read, poll and config
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
 * @version    SVN: $Id: averages.dfp.php 873 2008-02-06 20:16:29Z prices $
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
/**
 * Checks to see when the last sensor read was.  It it was mroe than an
 * hour ago it creates an alarm.
 *
 * @param object &$obj The alarm object to play with
 *
 * @return none
 */
function dailyReportLast(&$obj)
{
    $res = $obj->device->getWhere(
        "GatewayKey = ?",
        array($obj->config["script_gatewaykey"])
    );
    $types = array(
        "LastPoll" => "Last Poll",
        "LastConfig" => "Last Configuration",
        "LastHistory" => "Last History",
        "LastAnalysis" => "Last Analysis",
    );
    $days = array("0.04", "0.25", "0.5", "1", "3", "10", "many");
    $stats = array();
    foreach ($res as $row) {
        foreach ($types as $key => $type) {
            $date = strtotime($row[$key]);
            $done = false;
            foreach ($days as $d) {
                if ($d == "many") {
                    $stats[$key][$d]++;
                    break;
                }
                $time = time() - (int)((float)$d * 86400);
                if ($date > $time) {
                    $stats[$key][$d]++;
                    break;
                }
            }
        }
    }
    foreach ($types as $key => $type) {
        $title = $type;
        $text = "";
        foreach ($days as $d) {
            $cnt = $stats[$key][$d];
            if (empty($cnt)) {
                $cnt = 0;
            }
            $text .= $d." days old:  ".$cnt."\n";
        }
        $text .= "\n\n";
        $obj->dailyReportOutput($text, $title);
    }

}

$this->registerFunction("dailyReportLast", "dailyReport", "Last Readings");




?>
