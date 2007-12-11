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
 * @subpackage Misc
 * @copyright 2007 Hunt Utilities Group, LLC
 * @author Scott Price <prices@hugllc.com>
 * @version SVN: $Id$    
 *
 */

    if (empty($argv[1]) || !file_exists($argv[2]) || !file_exists($argv[3])) {
        die("Usage: ".$argv[0]." <version> <Code File> <Data File> <file Type> <firmwarePart> <hardwarePart> <status> <cvstag></cvstag>\r\n");    
    }
    require_once(dirname(__FILE__).'/../head.inc.php');
    require_once("firmware.inc.php");

    $firmware = new firmware($prefs['servers'], HUGNET_DATABASE, array("dbWrite" => true));
    $Info["FirmwareVersion"] = $argv[1];
    $Info["FirmwareCode"] = implode("", file($argv[2]));
    $Info["FirmwareData"] = implode("", file($argv[3]));
    $Info["FWPartNum"] = $argv[5];
    $Info["HWPartNum"] = $argv[6];
    $Info["Date"] = date("Y-m-d H:i:s");
    $Info["FirmwareFileType"] = $argv[4];
    $Info["FirmwareStatus"] = $argv[7];
    $Info["FirmwareCVSTag"] = $argv[8];
    $Info["Target"] = $argv[9];
    $return = $endpoint->db->AutoExecute('firmware', $Info, 'INSERT');
    if ($return) {
        print "Successfully Added\r\n";
        return(0);
    } else {

        print "Error (".$firmware->Errno."):  ".$firmware->Error."\r\n";
//        print "Query: ".$firmware->wdb->LastQuery."\r\n";
        return($firmware->Errno);
    }
/**
 * @endcond
 */
?>
