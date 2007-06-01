<?php
/**
	$Id: sizetest.php 52 2006-05-14 20:51:23Z prices $
	
	$Log: sizetest.php,v $
	Revision 1.1  2005/06/01 15:03:28  prices
	Moving from the 08/scripts directory.  This is the new home.
	
	Revision 1.2  2005/02/18 16:48:54  prices
	Many changes to accomodate the new controller boards, plus to move from hugnetd to seriald.
	
	Revision 1.1  2005/02/17 02:24:49  prices
	Periodic Checkin
	
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
	$packet = new EPacket($Info, TRUE);

	$index = 0;
	do {
//		$pkt["Data"] .= str_pad(dechex($index++), 2, "0", STR_PAD_LEFT);
		$pkt["Data"] = str_repeat("00", $index++);
		$return = $packet->SendPacket($Info, $pkt);		
	} while($return !== FALSE);
	$index--;
	print "Died at ".$index." Length\r\n";
	$packet->socket->Close();
	die();
	
?>
