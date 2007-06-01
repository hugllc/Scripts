<?php
/**
	$Id: monitor.php 221 2006-08-19 02:11:47Z prices $
	@file scripts/endpoints/unsolicited.php
	@brief Sits and waits for unsolicited packets to come in.
		
	$Log: monitor.php,v $
	Revision 1.3  2006/02/14 15:49:54  prices
	Periodic commit.
	
	Revision 1.2  2005/06/17 14:02:47  prices
	Fixed it so that it finds the main gateway.
	
	Revision 1.1  2005/06/05 13:19:32  prices
	Inception
	
	Revision 1.2  2005/06/01 20:44:52  prices
	Updated them to work with the new setup.
	
	Revision 1.1  2005/06/01 15:03:28  prices
	Moving from the 08/scripts directory.  This is the new home.
	
	Revision 1.6  2005/05/10 13:39:31  prices
	Periodic Checkin
	
	Revision 1.5  2005/04/12 02:01:44  prices
	I changed it a lot.  Now it doesn't spawn processes anymore.  It just sends out
	a configuration packet then waits to see what comes back.
	
	Revision 1.4  2005/04/05 13:37:27  prices
	Added lots of documentation.
	
	Revision 1.3  2005/02/18 18:40:25  prices
	Changed the number of retries to 10.
	
	Revision 1.2  2005/02/18 18:30:17  prices
	Moved the gateway checkin to packet.inc.php because it is used by both poll and unsolicited.
	
	Revision 1.1  2005/02/18 16:57:54  prices
	inceptionCVS: ----------------------------------------------------------------------
	

*/
/**
 * @cond SCRIPT	
*/
	print '$Id: monitor.php 221 2006-08-19 02:11:47Z prices $'."\n";
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
