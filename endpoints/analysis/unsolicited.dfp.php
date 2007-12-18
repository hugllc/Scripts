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
 * @version SVN: $Id$    
 *
 */

    function analysis_unsolicited(&$stuff, &$dev) {
        $sTime = microtime(true);
        global $verbose;

        if ($verbose > 1) print "analysis_unsolicited start\r\n";
        global $endpoint;

        $data = &$_SESSION['rawHistoryCache'];
        $stuff = &$_SESSION['analysisOut'];

        $stuff["Powerups"] = 0;
        $stuff["Boredom"] = 0;
        $stuff["Reconfigs"] = 0;

//        $plog = new container("", "PacketLog", "HUGNet");
//        $plog->AutoSETS();
//        $plog->SetRange("Date", $device["RangeStart"], $device["RangeEnd"]);
//        $plog->lookup($device["DeviceKey"], "DeviceKey");


//        $res = $endpoint->db->;
        $query = "SELECT * FROM ".$endpoint->packet_log_table." WHERE ".
                 " DeviceKey= ".$dev['DeviceKey']." AND (".$dev['datewhere'].")";
        $res = $endpoint->db->getArray($query);

        if (is_array($res)) {
            foreach ($res as $log) {
                switch($log["Command"]) {
                    case "5D":
                        $stuff["Reconfigs"]++;
                        break;
                    case "5E":
                        $stuff["Powerups"]++;
                        break;
                    case "5F";
                        $stuff["Boredom"]++;
                        break;
                    default:
                        break;
                }
            }
        }        
        
        $dTime = microtime(true) - $sTime;
        if ($verbose > 1) print "analysis_unsolicited end (".$dTime."s)\r\n";

    }


    $this->registerFunction("analysis_unsolicited", "Analysis");

?>