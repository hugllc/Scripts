<?php
/**
	$Id$
	@file scripts/test/sendpacket.php
	@brief generic script for sending packets to an endpoint
	
	$Log: checkInterp.php,v $
	Revision 1.3  2005/06/04 01:45:28  prices
	I think I finally got everything working again from changing the packet
	structure to accept multiple packets at the same time.
	
	Revision 1.2  2005/06/03 17:12:55  prices
	More stuff is being offloaded from the driver class into the specific or eDEFAULT drivers.
	This makes more sense.
	
	Revision 1.1  2005/06/01 15:03:28  prices
	Moving from the 08/scripts directory.  This is the new home.
	
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
	
	if (empty($argv[1])) {
		die("DeviceID must be specified!\r\n");	
	}

	$Info = $endpoint->getDevice($DeviceID, "ID");

	$query = "SELECT * FROM ".$endpoint->raw_history_table." WHERE ";
	$query .= " DeviceKey=".$Info["DeviceKey"];
	if (!is_null($pktCommand)) $query .= " AND sendCommand='".$pktCommand."' ";
	$query .= " AND Status='GOOD' ";
	$query .= " ORDER BY Date DESC ";
	$query .= " LIMIT 0, 2 ";

	$rHist = $endpoint->db->getArray($query);
    $rHist = array_reverse($rHist);

	$packet = $endpoint->InterpSensors($Info, $rHist);
	$endpoint->modifyUnits($packet, $Info, $dPlaces, $Info['params']['dType'], $Info['params']['Units']);
	var_dump($packet);
?>
