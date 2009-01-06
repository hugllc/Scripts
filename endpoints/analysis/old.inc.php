<?php
/**
 * Removes old endpoints from a gateway
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
 *
 */

/**
 * Here we are moving old devices off of gateways so that they don't bog
 * the system down.  If they have not reported in in a significant period
 * of time we just remove them from the gateway by setting the GatewayKey = 0
 *
 * @param object &$analysis The analysis object
 * @param array  &$devInfo  The devInfo array for the device
 *
 * @return null
 */
function Analysis_unassigned(&$analysis, &$devInfo)
{
    $sTime   = microtime(true);
    $verbose = $analysis->verbose;

    if ($verbose > 1) {
        print "analysis_unassigned start\r\n";
    }
    global $endpoint;

    if (($devInfo['PollInterval'] == 0) && ($devInfo["GatewayKey"] > 0)) {
        $days = 30;

        $cutoff = time() - ($days * 86400);
        if ((strtotime($devInfo["LastHistory"]) < $cutoff)
            && (strtotime($devInfo["LastPoll"]) < $cutoff)
            && (strtotime($devInfo["LastConfig"]) < $cutoff)
           ) {
            $info = array(
                "DeviceKey"  => $dev["DeviceKey"],
                "GatewayKey" => 0,
            );
            $analysis->device->update($info);
            $devInfo["GatewayKey"] = 0;
            print "Moved to unassigned devices\n";
        }
    }
    $dTime = microtime(true) - $sTime;
    if ($verbose > 1) {
        print "analysis_unassigned end (".$dTime."s)\r\n";
    }
}


$this->registerFunction("Analysis_unassigned", "preAnalysis");

?>