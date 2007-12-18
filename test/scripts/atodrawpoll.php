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
 * @subpackage Test
 * @copyright 2007 Hunt Utilities Group, LLC
 * @author Scott Price <prices@hugllc.com>
 * @version SVN: $Id$    
 *
 */

   if ($argc < 2) die("You must supply a device name\n");
    $DeviceID = $argv[1];

    include_once("blankhead.inc.php");
    print "Starting...\n";



    $servers = array();
    $servers[0]["Host"] = "localhost"; // Set to your server name or$
    $servers[0]["User"] = "Portal";  // Set to the database username
    $servers[0]["Password"] = "Por*tal"; // Set to the database password
    $servers[0]["AccessType"] = "RW";  // R for Read, W for Write, RW for both
    $servers[0]["db"] = "HUGnetLocal";
    $history = new container($servers, "History", "HUGnetLocal");
    $history->AutoSETS();
    $endpoint->device->lookup($DeviceID, "DeviceID");
    $dev = $endpoint->device->lookup[0];    
    while (1) {
        $lastminute = date("i");

        $sensors = $dev;        
                    
        $data = $endpoint->ReadMem($dev, "SRAM", 0x81, 10);
        $index = 0;
        if (!is_array($data["data"])) {
            for ($i = 0; $i < (strlen($data["rawdata"])/2); $i++) {
                $data["data"][] = hexdec(substr($data["rawdata"], ($i*2), 2));
            }                        
        }
        $sensors["Date"] = date("Y-m-d H:i:s");
        $sensors["RawData"] = $data["rawdata"];
        for ($key = 0; $key < 5; $key++) {
            $sensors["Data".$key] = $data["data"][$index];
            $sensors["Data".$key] += $data["data"][$index+1] << 8;
            $index += 2;
        }
        if (($sensors !== false) && (count($sensors) > 0)) {
            for ($i = 0; $i < count($data["data"]); $i+=2) {
                print (($data["data"][$i] + ($data["data"][$i+1]<<8))>>6)." ";
            }
/*
            if ($history->Add($sensors)) {
                print " Success ";
            } else {
                print " Failed to store data";
            }
*/
        } else {
            print " No data returned";
        }
        print "\n";
    }

    include_once("blanktail.inc.php");
    print "Finished\n";
?>
