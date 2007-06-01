<?php

   if ($argc < 2) die("You must supply a device name\n");
	$DeviceID = $argv[1];

	include_once("blankhead.inc.php");
	print "Starting...\n";



	$servers = array();
	$servers[0]["Host"] = "localhost"; // Set to your server name or$
	$servers[0]["User"] = "Portal";  // Set to the database username
	$servers[0]["Password"] = "Por*tal"; // Set to the database password
	$servers[0]["AccessType"] = "RW";  // R for Read, W for Write, RW for both
	$servers[0]["db"] = "HUGnetLocal";
	$history = new container($servers, "History", "HUGnetLocal");
	$history->AutoSETS();
	$endpoint->device->lookup($DeviceID, "DeviceID");
	$dev = $endpoint->device->lookup[0];	
	while (1) {
		$lastminute = date("i");

		$sensors = $dev;		
					
		$data = $endpoint->ReadMem($dev, "SRAM", 0x81, 10);
		$index = 0;
		if (!is_array($data["data"])) {
			for ($i = 0; $i < (strlen($data["rawdata"])/2); $i++) {
				$data["data"][] = hexdec(substr($data["rawdata"], ($i*2), 2));
			}						
		}
		$sensors["Date"] = date("Y-m-d H:i:s");
		$sensors["RawData"] = $data["rawdata"];
		for ($key = 0; $key < 5; $key++) {
			$sensors["Data".$key] = $data["data"][$index];
			$sensors["Data".$key] += $data["data"][$index+1] << 8;
			$index += 2;
		}
		if (($sensors !== FALSE) && (count($sensors) > 0)) {
			for($i = 0; $i < count($data["data"]); $i+=2) {
				print (($data["data"][$i] + ($data["data"][$i+1]<<8))>>6)." ";
			}
/*
			if ($history->Add($sensors)) {
				print " Success ";
			} else {
				print " Failed to store data";
			}
*/
		} else {
			print " No data returned";
		}
		print "\n";
	}

	include_once("blanktail.inc.php");
	print "Finished\n";
?>
