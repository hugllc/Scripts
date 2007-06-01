<?php
/**
	$Id: sendpacket.php 668 2007-02-27 04:07:05Z prices $
	@file scripts/test/sendpacket.php
	@brief generic script for sending packets to an endpoint
	
*/ 
/**
 * @cond	SCRIPT
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
    $endpoint->packet->_getAll = TRUE;
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
