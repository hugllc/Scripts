<?php
/**
	$Id$
	@file scripts/test/sendpacket.php
	@brief generic script for sending packets to an endpoint
	
	$Log: getSensors.php,v $
	Revision 1.1  2005/06/03 17:12:55  prices
	More stuff is being offloaded from the driver class into the specific or eDEFAULT drivers.
	This makes more sense.
	
	Revision 1.2  2005/06/01 20:44:52  prices
	Updated them to work with the new setup.
	
	Revision 1.1  2005/06/01 15:03:28  prices
	Moving from the 08/scripts directory.  This is the new home.
	
	Revision 1.4  2005/05/10 13:39:31  prices
	Periodic Checkin
	
	Revision 1.3  2005/04/05 13:37:27  prices
	Added lots of documentation.
	
	Revision 1.2  2005/02/18 16:48:54  prices
	Many changes to accomodate the new controller boards, plus to move from hugnetd to seriald.
	
	Revision 1.1  2005/02/17 02:24:49  prices
	Periodic Checkin
	
*/ 
/**
 * @cond	SCRIPT
*/
	require_once(dirname(__FILE__).'/../head.inc.php');
        $endpoint->packet->SNCheck(FALSE);

	
	if (empty($DeviceID)) {
		die("DeviceID must be specified!\r\n");	
	}

	$dev = $endpoint->getDevice($DeviceID, "ID");

	$dev["GatewayIP"] = $GatewayIP;
	$dev["GatewayPort"] = $GatewayPort;

	$endpoint->packet->verbose = $verbose;
	$pkt = $endpoint->ReadSensors($dev);

//	$config = $endpoint->InterpConfig($pkt);
	var_dump($pkt);

	die();
/**
 * @endcond
*/

?>
