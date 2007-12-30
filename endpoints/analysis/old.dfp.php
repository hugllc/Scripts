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
 *
 */

/** 
 * Here we are moving old devices off of gateways so that they don't bog
 * the system down.  If they have not reported in in a significant period
 * of time we just remove them from the gateway by setting the GatewayKey = 0
 *
 * @param array &$stuff Here
 * @param array &$dev   The devInfo array for the device
 *
 * @return void
 */
function analysis_unassigned(&$stuff, &$dev) 
{
    $sTime = microtime(true);
    global $verbose, $endpoint;

    if ($verbose > 1) print "analysis_unassigned start\r\n";
    global $endpoint;

    if (($dev['PollInterval'] == 0) && ($dev["GatewayKey"] > 0)) {
        $days = 30;

        $cutoff = time() - ($days * 86400);
        if ((strtotime($dev["LastHistory"]) < $cutoff) 
            && (strtotime($dev["LastPoll"]) < $cutoff)
            && (strtotime($dev["LastConfig"]) < $cutoff)
           ) {
            $query = "UPDATE ".$endpoint->device_table
                     ." SET GatewayKey=0 "
                     ." WHERE "
                     ." DeviceKey= ".$dev['DeviceKey'];
    
            $res = $endpoint->db->Execute($query);
            $_SESSION['devInfo']["GatewayKey"] = 0;
            print "Moved to unassigned devices\n";
        }
    }    
    $dTime = microtime(true) - $sTime;
       if ($verbose > 1) print "analysis_unassigned end (".$dTime."s)\r\n";
}


$this->registerFunction("analysis_unassigned", "preAnalysis");

?>