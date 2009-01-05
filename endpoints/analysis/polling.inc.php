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
 * Analysis plugin
 *
 * @param array &$analysis Here
 * @param array &$devInfo   The devInfo array for the device
 *
 * @return null
 */
function analysis_polling(&$analysis, &$devInfo)
{
    $sTime = microtime(true);
    global $verbose;

    if ($verbose > 0) print "analysis_polling start\r\n";

    $analysis->analysisOut["AveragePollTime"] = 0;
    $analysis->analysisOut["Polls"] = 0;
    $analysis->analysisOut['AverageReplyTime'] = 0;
    $analysis->analysisOut['Replies'] = 0;
    $lastpoll = 0;
    foreach ($analysis->rawHistoryCache as $key => $row) {
        if ($row["Status"] == "GOOD") {
            if ($lastpoll != 0) {
                   $analysis->analysisOut["Polls"]++;
                $analysis->analysisOut["AveragePollTime"] += (strtotime($row["Date"]) - $lastpoll)/60;
            }
        }
        $lastpoll = strtotime($row["Date"]);
        if ($row['ReplyTime'] > 0) {
            $analysis->analysisOut['AverageReplyTime'] += $row['ReplyTime'];
            $analysis->analysisOut['Replies']++;
        }
    }
    if ($analysis->analysisOut["Polls"] > 0) {
        $analysis->analysisOut["AveragePollTime"] /= $analysis->analysisOut["Polls"];
    }
    if ($analysis->analysisOut['Replies'] > 0) {
        $analysis->analysisOut['AverageReplyTime'] /= $analysis->analysisOut['Replies'];
    }
    $dTime = microtime(true) - $sTime;
    if ($verbose > 1) print "analysis_polling end (".$dTime."s) \r\n";

}


$this->registerFunction("analysis_polling", "Analysis9");

?>