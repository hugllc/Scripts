<?php
/**
	$Id: fastpoll.php 52 2006-05-14 20:51:23Z prices $
	file scripts/endpoints/fastpoll.php
	@brief Polls endpoints for data periodically.
	
	$Log: fastpoll.php,v $
	Revision 1.1  2005/06/01 15:03:28  prices
	Moving from the 08/scripts directory.  This is the new home.
	
	Revision 1.35  2004/12/09 00:12:16  prices
	Changed the check record function in e00391200 so that it no longer writes over RawData.
	
	Revision 1.34  2004/12/08 22:36:29  prices
	Changed DeviceName over to DeviceID.
	
	Revision 1.33  2004/12/07 22:47:24  prices
	Many fixes and it now does hourly and daily averages.
	
	Revision 1.32  2004/12/07 16:20:39  prices
	It now records a record as bad instead of not showing it.
	
	Revision 1.31  2004/12/07 15:49:42  prices
	Many changes in how things work and a lot of bug fixes.
	
	Revision 1.30  2004/12/04 18:34:11  prices
	Lots of fixes
	
	Revision 1.29  2004/11/29 20:03:46  prices
	Periodic checkin.
	
	Revision 1.28  2004/11/20 04:35:19  prices
	A multitude of changes.  This is a periodic checkin.
	
	Revision 1.27  2004/10/11 20:55:38  prices
	Fixed a lot of stuff.
	
	Revision 1.26  2004/08/11 14:12:15  prices
	It should track failures now.
	
	Revision 1.25  2004/08/11 13:46:08  prices
	Periodic checkin
	
	Revision 1.24  2004/07/17 12:44:54  prices
	Fixed host for database.  I also set it so that it will only process 4 powerup packets, then go on.  It
	also ignores powerup packets from FFFFFF.
	
	Revision 1.23  2004/07/13 13:34:31  prices
	It now randomizes the array when it first loads it from the database.
	
	Revision 1.22  2004/07/13 00:20:05  prices
	Major changes.  It now really calculates the time an endpoint should be polled instead of just
	taking the mod of the minutes.  It also stops trying to check endpoints that powered up after
	3 failed attempts.
	
	Revision 1.21  2004/03/24 17:37:06  prices
	*** empty log message ***
	
	Revision 1.20  2004/03/24 17:12:42  prices
	Straightened out the printing of the Id string.
	
	Revision 1.18  2004/03/24 17:09:04  prices
	Added the header.
	

*/

	$dfportal_no_session = TRUE;
	include_once("blankhead.inc.php");

	print '$Id: fastpoll.php 52 2006-05-14 20:51:23Z prices $'."\n";
	print "Starting...\n";
	$GatewayKey = FALSE;
	
	// This tries to automatically determine what Gateway to use, since none was supplied.
	if ($argc < 2) {
		$gw = $endpoint->device->gateway->Find(TRUE);
		if (is_array($gw)) {
			$GatewayKey = $gw["GatewayKey"];
		}
	} else {
		// We were passed a gateway key, so use it.
		$GatewayKey = $argv[1];
	}

	if (($GatewayKey == FALSE) | ($GatewayKey == 0)) die("You must supply a gateway key\n");
	
	do {
		$endpoint->device->gateway->lookup($GatewayKey, "GatewayKey, GatewayName");
		if ($endpoint->device->gateway->lookup[0]["BackupKey"] != 0) {
			$GatewayKey = $endpoint->device->gateway->lookup[0]["BackupKey"];
		}
	} while ($endpoint->device->gateway->lookup[0]["BackupKey"] != 0);
	$gw = array();
	$gw[0] = $endpoint->device->gateway->lookup[0];
	$endpoint->device->gateway->lookup($GatewayKey, "BackupKey");
	foreach($endpoint->device->gateway->lookup as $thegw) {
		$gw[] = $thegw;
	}
	
	if (!is_array($gw[0])) die("Gateway Not found");

	
	$servers = array();
	$servers[0]["Host"] = "localhost"; // Set to your server name or$
	$servers[0]["User"] = "PortalW";  // Set to the database username
	$servers[0]["Password"] = "Por*tal"; // Set to the database password
	$servers[0]["AccessType"] = "RW";  // R for Read, W for Write, RW for both
	$servers[0]["db"] = "HUGnetLocal";
	$history = new container($servers, "History", "HUGnetLocal");
	$history->AutoSETS();

	$gwindex = array();
	$ep = array();
	$PollTime = array();
	$Failures = array();
	$lastminute = -1;
	while (1) {
		$forceregen = FALSE;
		
		// Get all the unsolicitied packets
		foreach($gw as $gate) {
			while(($tmp = $endpoint->socket->GetUnsolicited($gate)) != FALSE) {
				if (trim(strtoupper($tmp["from"])) != "FFFFFF") {
					if (!isset($Packets[trim(strtoupper($tmp["from"])).".".trim(strtoupper($tmp["command"]))])) {
						$Packets[trim(strtoupper($tmp["from"])).".".trim(strtoupper($tmp["command"]))] = $tmp;
					}
					$Packets[trim(strtoupper($tmp["from"])).".".trim(strtoupper($tmp["command"]))]["Gateways"][] = $gate;
					
				}
			}
		}
		// Deal with the unsolicited packets.
		if (is_array($Packets)) {
			$Done = 0;
			foreach($Packets as $key => $Packet) {
				$found = FALSE;
				foreach($Packet["Gateways"] as $gate) {
					print "Dealing with Unsolicited packet from ".$Packet["from"]." ";
					if ($endpoint->Unsolicited($Packet, $gate["GatewayKey"])) {
						if (isset($endpoint->Info[strtoupper($Packet["from"])])) {
							unset($PollTime[$endpoint->Info[strtoupper($Packet["from"])]["DeviceKey"]]);
							unset($gwindex[$endpoint->Info[strtoupper($Packet["from"])]["DeviceKey"]]);
						}
						unset($Packets[$key]);
						$forceregen = TRUE;
						$found = TRUE;
						print " Done ";
						break;
					}
				}
				if ($found !== TRUE) {
					print " Failed ";
					$Packets[$key]["RetryCount"]++;
					if ($Packets[$key]["RetryCount"] > 3) {
						print " Giving up ";
						unset($Packets[$key]);
					}
				}
				$Done++;
				print "\n";
				if ($Done > 4) break;
			}
		}
		// Regenerate our endpoint information
		if ((($lastminute % 10) == 0) || (count($ep) < 1) || $forceregen) {
			print "Getting endpoints for Gateway #".$GatewayKey."\n";
			$endpoint->device->lookup($GatewayKey, "GatewayKey");
			if (count($endpoint->device->lookup) > 0) {
				$ep = $endpoint->device->lookup;
				shuffle($ep);  // This randomizes it.
			}
			$gwindex = array();
		}

			
		foreach ($ep as $key => $val) {
			if ($val["PollInterval"] <= 0) {
				// THis removes any that aren't being polled.
				unset($ep[$key]);  
			} else {
				if (!isset($PollTime[$val["DeviceKey"]])) {
					$PollTime[$val["DeviceKey"]] = GetNextPoll($val["LastPoll"], $val["PollInterval"]);
				}
				if (!isset($gwindex[$val["DeviceKey"]])) {
					$gwindex[$val["DeviceKey"]] = 0;
				}
			}
		}

		// Close the socket (this is the logical end of our loop
//		$endpoint->socket->close();

		/*
			This section pauses until the next minute.		
		*/
/*
		print "Pausing...\n";
		while(date("i") == $lastminute) {
			sleep(1);
		}
*/
		/*
			This section pings the servers and checks to see if we should run.  It sets
			$dopoll to TRUE if we shoudl poll.
		*/
/*
		print "Checking in with the Gateways... ";
		foreach($gw as $key => $gate) {
			if (!isset($gate["PingKey"])) {
				$PingKey = 255;
			} else {
				$PingKey = $gate["PingKey"];
			}
			print $gate["GatewayName"]." : ";
			$Poll[$key] = $endpoint->device->gateway->PingStat($gate, "poll.php__".$GatewayKey, $PingKey);
			if (isset($Poll[$key]["pingkey"])) {
				$gw[$key]["PingKey"] = $Poll[$key]["pingkey"];
			}
			$dopoll = TRUE;
		}
		print "Done \r\n";
*/		
		$dopoll = TRUE;

		// Here is the actual polling
		$lastminute = date("i");
		if ($dopoll) {
			foreach($ep as $key => $dev) {
				print $dev["DeviceID"]." (".$dev["Driver"].") -> ".date("Y-m-d H:i:s", $PollTime[$dev["DeviceKey"]])." <-> ".date("Y-m-d H:i:s");
//				if ($PollTime[$dev["DeviceKey"]] <= time()) { 
					$count = 0;
					do {
						$count++;
						$sensors = array();
						$dev["GatewayIP"] = $gw[$gwindex[$dev["DeviceKey"]]]["GatewayIP"];	
						$dev["GatewayPort"] = $gw[$gwindex[$dev["DeviceKey"]]]["GatewayPort"];
						print  " [".$gw[$gwindex[$dev["DeviceKey"]]]["GatewayName"]."] ->";
						$sensors = $endpoint->ReadSensors($dev);
						if (($sensors !== FALSE) && (count($sensors) > 0)) {
							$failures[$dev["DeviceKey"]] = 0;
							if ($lastindex[$sensors["DeviceKey"]] != $sensors["DataIndex"]) {
								if ($history->Add($sensors)) {
									print " Success ";
									$PollTime[$dev["DeviceKey"]] = GetNextPoll(time(), $dev["PollInterval"]);
									//This rotates through the array, so we are not always doing things in the same order
									$ep[] = $ep[$key];
									unset($ep[$key]);
								} else {
									print " Failed to store data (".$history->wdb->Errno."): ".$history->wdb->Error;
									print strip_tags(get_stuff($history->wdb));							
								}
							} else {
								print " Data Index Identical ";
							}
						} else {
							print " No data returned (".$failures[$dev["DeviceKey"]].")";
							$gwindex[$dev["DeviceKey"]]++;
							if (!isset($gw[$gwindex[$dev["DeviceKey"]]])) $gwindex[$dev["DeviceKey"]] = 0;
						}
					} while (($count < count($gw)) && ($sensors === FALSE));
					if ($sensors === FALSE) {
						$failures[$dev["DeviceKey"]]++;
						$PollTime[$dev["DeviceKey"]] = GetNextPoll(time(), $dev["PollInterval"], $failures[$dev["DeviceKey"]]);
					}
					$lastindex[$sensors["DeviceKey"]] = $sensors["DataIndex"];	
					print "\n";
//				} else {
//					print " Not Time\n";
//				}
				if (date("i") != $lastminute) {
					print "Minute expended.  Going to the next one.\n";
					break;
				}
			}

			// This section updates the database 
			if (function_exists(pcntl_waitpid)) {
				$ccheck = 0;
				if (isset($child)) {
					$ccheck = pcntl_waitpid($child, $status, WNOHANG);
				}
				if ($ccheck >= 0) {
					$child = pcntl_fork();
					if ($child == -1) {
					} else if ($child == 0) {
						include("updatedb.php");
						die();
					}
				}
			} else {
				print "Automatic updating not enabled.  Please enable 'pcntl' in PHP\r\n";
			}
					
		} else {
			print "Skipping Poll.  Polling being done by ".$Poll["Ident"]." on ".$Poll["IP"]."\r\n";
		}
	}	

	include_once("blanktail.inc.php");
	print "Finished\n";

function GetNextPoll($time, $Interval, $failures=FALSE) {
	if (!is_numeric($time)) {
		$time = strtotime($time);
	}

	$sec = 0; //date("s", $time);
	$min = date("i", $time);
	$hour = date("H", $time);
	$mon = date("m", $time);
	$day = date("d", $time);
	$year = date("Y", $time);

	if ($failures === FALSE) {
		$nexttime = mktime($hour, ($min + $Interval), $sec, $mon, $day, $year);
	} else {
		$NewInt = (int)($failures / 5) * $Interval; 
		if ($NewInt > 240) $NewInt = 240;
		$nexttime = mktime($hour, ($min + $NewInt), $sec, $mon, $day, $year);	
	}
	return($nexttime);
}

?>	

		
		
		
