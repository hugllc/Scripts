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
    require("packet.inc.php");

    if (empty($argv[1])) {
        die("DeviceID must be specified!\r\n");    
    }
    $pkt = array();
    $Info["DeviceID"] = $argv[1];
    $pkt["To"] = $argv[1];
    $pkt["Command"] = "02";


    if (isset($argv[2])) {
        $Info["GatewayIP"] = $argv[2];
    } else {
        $Info["GatewayIP"] = "127.0.0.1";
    }
    if (isset($argv[3])) {
        $Info["GatewayPort"] = $argv[3];
    } else {
        $Info["GatewayPort"] = 1200;
    }
    $Info["GatewayKey"] = 1;
    $packet = new EPacket($Info, true);

    $index = 0;
    do {
//        $pkt["Data"] .= str_pad(dechex($index++), 2, "0", STR_PAD_LEFT);
        $pkt["Data"] = str_repeat("00", $index++);
        $return = $packet->SendPacket($Info, $pkt);        
    } while($return !== false);
    $index--;
    print "Died at ".$index." Length\r\n";
    $packet->socket->Close();
    die();
    
?>
