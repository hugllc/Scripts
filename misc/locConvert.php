<?php
/**
 *   <pre>
 *   HUGnetLib is a library of HUGnet code
 *   Copyright (C) 2007 Hunt Utilities Group, LLC
 *   
 *   This program is free software; you can redistribute it and/or
 *   modify it under the terms of the GNU General Public License
 *   as published by the Free Software Foundation; either version 3
 *   of the License, or (at your option) any later version.
 *   
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *   
 *   You should have received a copy of the GNU General Public License
 *   along with this program; if not, write to the Free Software
 *   Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *   </pre>
 *
 *   @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *   @package Scripts
 *   @subpackage Misc
 *   @copyright 2007 Hunt Utilities Group, LLC
 *   @author Scott Price <prices@hugllc.com>
 *   @version $Id: unitConversion.inc.php 369 2007-10-12 15:05:32Z prices $    
 *
 */
	print '$Id: monitor.php 121 2007-07-19 13:13:02Z prices $'."\n";
	print "Starting...\n";


	require_once(dirname(__FILE__).'/../head.inc.php');

    $query = "select * from location";
    $oldLoc = array();
    $loc = $endpoint->db->getArray($query);
    if (is_array($loc)) {
        foreach($loc as $row) {
            foreach($row as $key => $tLoc) {
                $key = trim($key);
                if (strtolower(substr($key, 0, 3)) == "loc") {
                    if (!empty($tLoc)) {
                        $nKey = (int) substr($key, 3);
                        $oldLoc[$row["DeviceKey"]][$nKey] = $tLoc;
                    }
                }
            }
        }
    }

    foreach ($oldLoc as $DeviceKey => $loc) {
        print $DeviceKey;
        $devInfo = $endpoint->device->getDevice($DeviceKey);
        foreach($loc as $key => $l) {
            $devInfo['params']['Loc'][$key] = $l;
        }
        $ret = $endpoint->device->setParams($DeviceKey, $devInfo['params']);
        if ($ret) {
            print " Done ";
        } else {
            print " Failed ";
        }
        print "\n";
    }

	print "Finished\n";
/**
 * @endcond	
*/


?>
