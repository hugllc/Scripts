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
 *   @subpackage Test
 *   @copyright 2007 Hunt Utilities Group, LLC
 *   @author Scott Price <prices@hugllc.com>
 *   @version $Id$    
 *
 */
    $pktData = "";
    require_once(dirname(__FILE__).'/../head.inc.php');
	$endpoint->packet->SNCheck(FALSE);

	if (empty($DeviceID)) {
		die("DeviceID must be specified!\r\n");	
	}
	$pkt = array();
	$Info["DeviceID"] = $DeviceID;
	$pkt["To"] = strtoupper($DeviceID);
	if (isset($pktCommand)) {
		$pkt["Command"] = $pktCommand;
	} else {
		$pkt["Command"] = "02";
	}

	$pkt["Data"] = $pktData;


	if (!empty($GatewayIP)) {
		$Info["GatewayIP"] = $GatewayIP;
	} else {
	    $dev = $endpoint->getDevice($pkt["To"], "ID");

		if (is_array($dev["Gateway"])) {
			$Info["GatewayIP"] = $dev["Gateway"]["GatewayIP"];
			$Info["GatewayPort"] = $dev["Gateway"]["GatewayPort"];
			$Info["GatewayKey"] = $dev['Gateway']["GatewayKey"];
		} else {
			$Info["GatewayIP"] = "127.0.0.1";
		}
	}

    if (!isset($Info['GatewayPort'])) $Info['GatewayPort'] = $GatewayPort;

	if (!isset($Info['GatewayKey'])) $Info["GatewayKey"] = 1;
    $endpoint->packet->verbose = $verbose;
    if ($testMode); 
    $endpoint->packet->getAll(TRUE);
	$pkt = $endpoint->packet->SendPacket($Info, $pkt);

    if (is_array($pkt)) {
    	foreach($pkt as $p) {
    		if (($p["From"] == $p["SentTo"]) || $p["group"]) {
    			print_r($p);
    			if (is_array($p["Data"])) {
    				foreach($p["Data"] as $key => $val) {
    					print $key ."\t=> ".$val."\t=> ".dechex($val)."\t=> ".str_pad(decbin($val), 8, "0", STR_PAD_LEFT)."\n";
    				}
    			}
    		}
    	}
    } else {
        print "Nothing Returned\r\n";
    }
	$endpoint->packet->Close($Info);
	die();
/**
 * @endcond
*/

?>
