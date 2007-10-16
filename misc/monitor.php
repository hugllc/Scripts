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
 *   @version $Id$    
 *
 */
	print '$Id$'."\n";
	print "Starting...\n";


	require_once(dirname(__FILE__).'/../head.inc.php');

	$prefs =& $conf;

    if ($GatewayIP == FALSE) {

    } else {
        $gw = array(
            "GatewayKey" => 0,
            "GatewayIP" => $GatewayIP,
            "GatewayName" => $GatewayIP,
            "GatewayPort" => $GatewayPort,
        );            
    }
	$ep = array();
	$getDevInfo = TRUE;
	$minuteCounter = 0;
	$packets = array();

    $endpoint->packet->verbose = $verbose;	
	
	//Only try twice per server.
	$endpoint->socket->Retries = 2;
	$endpoint->socket->PacketTimeout = 4;

	print "Waiting for packets\r\n";
	while (1) {
	
		$pkt = $endpoint->packet->monitor($gw);
		if ($pkt !== FALSE) {
			print "From: ".$pkt['From'];
			print " -> To: ".$pkt['To'];
			print "  Command: ".$pkt['Command']."\r\n";
			if (!empty($pkt['RawData'])) print "Data: ".$pkt['RawData']."\r\n";
		}

	}	

	include_once("blanktail.inc.php");
	print "Finished\n";
/**
 * @endcond	
*/


?>
