<?php
/**
	$Id$
	@file scripts/test/sockettest.php
	@brief Tests the socket interface.
	
	$Log: sockettest.php,v $
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
	$dfportal_no_session = TRUE;
	$extra_includes[] = "process.inc.php";
	$extra_includes[] = "packet.inc.php";
	$extra_includes[] = "socket.inc.php";
	include_once("blankhead.inc.php");

	$server = $argv[1];

	$endpoint->device->lookup("0000AC", "DeviceID");
	$dev = $endpoint->device->lookup[0];

	$packet = new EPacket(TRUE, $dev["GatewayIP"]);

	$packet->socket->SetPort(0);
	print ($packet->Ping($dev));
	$packet->socket->Close();
	die();
	
	$string = "";
	$buffers = array();
	while(1) {
		$packet->socket->PeriodicCheck();
		$string = $packet->socket->Read();
		if (stristr(trim($string), ":") !== FALSE) {
			$pair = explode(":", trim($string));
			if (is_numeric($pair[0])) {
				$index = (int) $pair[0];
				$buffers[$index] .= strtoupper($pair[1]);
				$pkt = stristr($buffers[$index], "5A5A");
				if (strlen($pkt) > 11) {
					while (substr($pkt, 0, 2) == "5A") $pkt = substr($pkt, 2);
					$len = hexdec(substr($pkt, 14, 2));
					if (strlen($pkt) >= ((9 + $len)*2)) {
						$pkt = substr($pkt, 0, (9+$len)*2);

						$chksum = 0;
						for($i = 0; $i < ((9+$len)*2); $i+=2) {
							$chksum ^= hexdec(substr($pkt, $i, 2));
						}
						print $pkt." - ".$chksum."\r\n";
						$buffers[$index] = "";
		
					}
				}
			} else {
				if (trim($string) != "") {
					print "Got: ".$string."\r\n";
				}
			}
		} else {
			if (trim($string) != "") {
				print "Got: ".$string."\r\n";
			}
		}
	}

	$packet->socket->Close();
/**
 * @endcond
*/
?>
