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
	require("EPacket.php");
	require "../head.inc.php";

	$Info["DeviceID"] = $DeviceID;
	$Info["GatewayKey"] = 1;
	if (isset($GatewayIP)) {
		$Info["GatewayIP"] = $GatewayIP;
	} else {
		$Info["GatewayIP"] = "127.0.0.1";
	
	if (isset($GatewayPort)) {
		$Info["GatewayPort"] = $GatewayPort;
	} else {
		$Info["GatewayPort"] = 1200;
	}
	$Info["GatewayKey"] = 1;
	$verbose = TRUE;
	$packet = new EPacket($Info, $verbose);

	$pkt = $packet->Ping($Info, TRUE);
	print_r($pkt);
	$packet->close($Info['GatewayKey']);
	die();
/**
 * @endcond
*/
	
?>
