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
 * @category   Scripts
 * @package    Scripts
 * @subpackage Analysis
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$    
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
/**
 * Analysis plugin
 *
 * @param array &$here   Here
 * @param array &$device The devInfo array for the device
 *
 * @return null
 */
function analysis_polling(&$here, &$device) 
{
    $sTime = microtime(true);
    global $verbose;

    if ($verbose > 1) print "analysis_polling start\r\n";

    $data = &$_SESSION['rawHistoryCache'];
    $stuff = &$_SESSION['analysisOut'];
    $stuff["AveragePollTime"] = 0;
    $stuff["Polls"] = 0;
    $stuff['AverageReplyTime'] = 0;
    $stuff['Replies'] = 0;
    $lastpoll = 0;
    foreach ($data as $key => $row) {
        if ($row["Status"] == "GOOD") {
            if ($lastpoll != 0) {
                   $stuff["Polls"]++;
                $stuff["AveragePollTime"] += (strtotime($row["Date"]) - $lastpoll)/60;
            }
        }
        $lastpoll = strtotime($row["Date"]);
        if ($row['ReplyTime'] > 0) {
            $stuff['AverageReplyTime'] += $row['ReplyTime'];
            $stuff['Replies']++;
        }
    }
    if ($stuff["Polls"] > 0) {
        $stuff["AveragePollTime"] /= $stuff["Polls"];
    }
    if ($stuff['Replies'] > 0) {
        $stuff['AverageReplyTime'] /= $stuff['Replies'];
    }
    $dTime = microtime(true) - $sTime;
    if ($verbose > 1) print "analysis_polling end (".$dTime."s) \r\n";

}


$this->registerFunction("analysis_polling", "Analysis");

?>