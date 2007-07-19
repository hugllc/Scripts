<?php
/**
	$Id$
	@file scripts/test/ping.php
	@brief Pings an endpoint
	
	$Log: ping.php,v $
	Revision 1.1  2005/06/01 15:03:28  prices
	Moving from the 08/scripts directory.  This is the new home.
	
	Revision 1.3  2005/05/10 13:39:31  prices
	Periodic Checkin
	
	Revision 1.2  2005/04/05 13:37:27  prices
	Added lots of documentation.
	
	Revision 1.1  2005/02/17 02:24:49  prices
	Periodic Checkin
	
*/
/**
 * @cond	SCRIPT
*/
	require("packet.inc.php");

	if (empty($argv[1])) {
		die("DeviceID must be specified!\r\n");	
	}

	$Info["DeviceID"] = $argv[1];
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
	$packet = new EPacket($Info["GatewayIP"], $Info["GatewayPort"], TRUE);

	$pkt = $packet->Ping($Info, 0);
	print_r($pkt);
	$packet->socket->Close();
	die();
/**
 * @endcond
*/
	
?>
