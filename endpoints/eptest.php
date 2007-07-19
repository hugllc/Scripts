<?php
/**
	$Id$
	@file scripts/endpoints/poll.php
	@brief Polls endpoints for data periodically.
	
	$Log: poll.php,v $
	Revision 1.30  2006/03/09 17:20:19  prices
	Updated the version
	
	Revision 1.29  2006/03/09 17:14:40  prices
	It now sets the LastPoll date correctly.
	
	Revision 1.28  2006/03/09 16:50:04  prices
	Fixed an error.
	
	Revision 1.27  2006/03/09 16:29:35  prices
	Updated the version.
	
	Revision 1.26  2006/03/09 16:24:20  prices
	It now randomizes the endpoints without killing the array keys.
	
	Revision 1.25  2006/03/08 15:47:26  prices
	It now inserts new records into the devices table
	
	Revision 1.24  2006/02/28 21:30:17  prices
	Fixed a bug where it wouldn't talk with other gateways if it wasn't
	polling.
	
	Revision 1.23  2006/02/28 20:14:07  prices
	Fixed a problem with getting the configuration for other gateways that are running.
	
	Revision 1.22  2006/02/28 15:52:56  prices
	Fixed it so that a slave gateway can be used instead of the master only.
	
	Revision 1.21  2006/02/27 17:50:42  prices
	Periodic checkin
	
	Revision 1.20  2006/02/14 15:49:54  prices
	Periodic commit.
	
	Revision 1.19  2005/11/12 03:11:53  prices
	Fixed a couple of things.
	
	

*/

/**
 * @cond SCRIPT	
*/

    $GatewayKey=3;
    define("EPTEST_VERSION", "0.0.5");
    define("EPTEST_PARTNUMBER", "0039260450");  //0039-26-04-P

	print '$Id$'."\n";
    print 'eptest.php Version '.EPTEST_VERSION."\n";
	print "Starting...\n";

	require_once(dirname(__FILE__).'/../head.inc.php');
    require_once(HUGNET_INCLUDE_PATH.'/plog.inc.php');


//    $endpoint->packet->_getAll = TRUE;
    $endpoint->packet->SNCheck(FALSE);

	//Only try twice per server.
	$endpoint->socket->Retries = 2;
	$endpoint->socket->PacketTimeout = 6;
    $firmware = new firmware($endpoint->db);

    $query = "SELECT * FROM endpoints WHERE Obsolete=0";      
    $res = $endpoint->db->getArray($query);

    while (empty($tester)) {
        fwrite(STDOUT, "Please enter your name:  ");
        $tester = trim(fgets(STDIN));
    }

    while(empty($hwPart)) {
        fwrite(STDOUT, "Hardware Part Number:  \n");
        foreach($res as $k => $f) {
            fwrite(STDOUT, $k.".  ".$f['HWPartNum']."\n");
        }
        fwrite(STDOUT, "? (0 - ".$k.") ");
        $hwChoice = (int) fgets(STDIN);
        if (isset($res[$hwChoice])) {
            $hwPart = $res[$hwChoice];
        }
    }
    print "Using hardware part number '".$hwPart['HWPartNum']."'\n";
    foreach(explode("\n", $hwPart['Parameters']) as $val) {
        $value = explode(":", $val);
        $key = trim(strtolower($value[0]));
        $value = trim($value[1]);
        $hwPart['Param'][$key] = $value;
    }
    if (!isset($hwPart['Param']['firmware'])) {

        $res = GetFirmwareFor($hwPart['HWPartNum']);
        if (is_array($res) && (count($res) > 0)) {
            foreach($res as $k => $f) {
                if (!isset($fw[$f['FWPartNum']])) {
                    $fw[$f['FWPartNum']] = $f;
                    $fwIndex[] = $f['FWPartNum'];
                }
            }
            if (count($fw) > 1) {
                while (empty($fwPart)) {
                    fwrite(STDOUT, "Choose a Firmware:  \n");
                    foreach($fwIndex as $k => $f) {
                        fwrite(STDOUT, $k.".  ".$f."\n");
                    }
                    fwrite(STDOUT, "? (0 - ".$k.") ");
                    $fwChoice = (int) fgets(STDIN);
                    if (isset($fwIndex[$fwChoice])) {
                        $fwPart = $fw[$fwIndex[$fwChoice]];
                    }
                }
            } else {
                $fwPart = $fw[$fwIndex[0]];
            }
        } else {
            print "Part number '".$hwPart."' was not found\n";
            $hwPart = "";
        }
    } else {
        $fwPart = $firmware->    GetLatestFirmware($hwPart['Param']['firmware']);
    }
    print "Using firmware part number '".$fwPart['FWPartNum']."' v".$fwPart['FirmwareVersion']."\n";
    while (empty($startSN)) {
        fwrite(STDOUT, "Starting Serial Number (in Hexadecimal):  ");
        $startSN = hexdec(fgets(STDIN));
    }
    $SN = $startSN;
    $pInfo = array(
        'GatewayIP' => $GatewayIP,
        'GatewayPort' => $GatewayPort,
        'GatewayKey' => $GatewayKey,
    );

    print "Setting up the packet interface...";
    $endpoint->packet->connect($pInfo);
    print " Done \n";
    print "Checking the Controller(s)... ";

    $cont = getControllers();
    if (count($cont) > 0) {
        print implode(" ", array_keys($cont));
    }
    print " Done \n";
	while (1) {
      	$Prog = 'uisp -dprog=dapa -v=0 -dpart='.$hwPart['Param']['cpu'].' -dlpt=/dev/parport0';

        // Start the device Info array
        $DeviceID = strtoupper(dechex($SN));
        $DeviceID = str_pad($DeviceID, 6, '0', STR_PAD_LEFT);
        $DeviceID = substr($DeviceID, 0, 6);

        $dev = $pInfo;
        $dev['DeviceID'] = $DeviceID;

        print "\n\nUsing 0x".$DeviceID." for the next device\n";
	    print "Insert the device in the tester.\n";
	    print "Press <enter> to continue, q<enter> to quit\n";

        $input = fgets(STDIN);
	    if (trim(strtolower($input)) == "q") break;
        $testStart = time();        


        
        // SET the fuses
        print "Setting Fuses...";
    	$fuse = ' --wr_fuse_h='.$hwPart['Param']['fusehigh'];
    	if ($verbose) print "\nUsing: ".$Prog.$fuse."\n";
    	exec($Prog.$fuse, $out, $pass['FuseHigh']);
        print " Low ";

    	$fuse = ' --wr_fuse_l='.$hwPart['Param']['fuselow'];
    	if ($verbose) print "\nUsing: ".$Prog.$fuse."\n";
    	exec($Prog.$fuse, $out, $pass['FuseLow']);
        print " High ";

        if (isset($hwPart['Param']['fuseextended'])) {
        	$fuse = ' --wr_fuse_e='.$hwPart['Param']['fuseextended'];
        	if ($verbose) print "\nUsing: ".$Prog.$fuse."\n";
        	exec($Prog.$fuse, $out, $pass['FuseExtended']);
            print " Extended ";
        }
        if (isset($hwPart['Param']['lockbits'])) {
        	$fuse = ' --wr_lock='.$hwPart['Param']['lockbits'];
        	if ($verbose) print "\nUsing: ".$Prog.$fuse."\n";
        	exec($Prog.$fuse, $out, $pass['FuseExtended']);
            print " Lock Bits ";
        }
        print " Done \n";


        // Write the serial Number
        print "Writing Serial Number 0x".$DeviceID."...";
        $tempname = writeSREC($SN, $hwPart['HWPartNum']);
    	
    	$eeprom = ' --segment=eeprom --upload --verify if='.$tempname;
    
    	if ($verbose) print "\nUsing: ".$Prog.$eeprom."\n";
    	exec($Prog.$eeprom, $out, $pass['Serialnum']);
        print " Done \n";
        print "Writing Firmware Program... ";

    	// Program the flash
    	$tempname = tempnam("/tmp", "uisp");
    	
    	$fp = fopen($tempname, "w");
    	fwrite($fp, $fwPart['FirmwareCode']);
    	fclose($fp);
    	$flash = ' --segment=flash --erase --upload --verify if='.$tempname;
    
    	if ($verbose) print "\nUsing: ".$Prog.$flash."\n";
    	exec($Prog.$flash, $out, $pass['Program']);
    	unlink($tempname);
        print " Done \n";
        print "Writing Firmware Data... ";
    
    	// Program the E2
    	$tempname = tempnam("/tmp", "uisp");
    	
    	$fp = fopen($tempname, "w");
    	fwrite($fp, $fwPart['FirmwareData']);
    	fclose($fp);
    	$eeprom = ' --segment=eeprom --upload --verify if='.$tempname;
    
    	if ($verbose) print "\nUsing: ".$Prog.$eeprom."\n";
    	exec($Prog.$eeprom, $out, $pass['Data']);
    	unlink($tempname);
        print " Done \n";

        sleep(2);

        // Get the configuration
        $pass['Configuration'] = -1;

		print "Pinging ".$dev["DeviceID"]." ";
		$pkt = $endpoint->packet->ping($dev, TRUE);
		print "Done \r\n";
		
		print "Checking the configuration of ".$dev["DeviceID"]." ";
		$pkt = $endpoint->ReadConfig($dev);
		if ($pkt !== FALSE) {
			foreach($pkt as $p) {
				if ($p !== FALSE) {
				    if ($p["Reply"]) {
				        if ($p['sendCommand'] == "5C") {
                            $pass['Configuration'] = 0;
                            $newConfig = $endpoint->InterpConfig(array($p));
                            $newConfig = $newConfig[0];
                            $dev = array_merge($dev, $newConfig);
                            $configPkt = $p;
                        }
    				}
				}
			}

		} else {
            $pass['Configuration'] = 2;
		}
        print "Done \r\n";

        if (method_exists($endpoint->drivers[$dev['Driver']], "loadProgram")) {
            print "Loading program...\n";
            $pass['Load Program'] = !(bool)($endpoint->drivers[$dev['Driver']]->loadProgram($dev, $dev, $fwPart['FirmwareKey']));
    		print "Done\r\n";
	    }




        // Print the results
        $results = "Results:\n";
        $failed = FALSE;
        foreach($pass as $name => $p) {
            $results .= $name.": ";
            if ($p == 0) {
                $results .= "Pass";
            } else {
                $results .= "Failed";
                $failed = TRUE;
            }
            $results .= "\n";
        }        

        print "Saving Test Log...";
        $log = array(
            'HWPartNum' => $hwPart['HWPartNum'],
            'LogDate' => date("Y-m-d H:i:s"),
            'Log' => $results,
            'PassedTest' => !$failed,
            'Tester' => $tester,
            'testVersion' => EPTEST_VERSION,
        );
        if ($failed == FALSE) {
            $log['Log'] .= "SN: ".$dev['DeviceID']."\n";
        }
        $log['Log'] .= "Time: ".(time() - $testStart)."\n";
	    $return = $endpoint->db->AutoExecute('testLog', $log, 'INSERT');


        print "\n".$results;

        

        if ($failed) {
            print "\n\nTHIS DEVICE FAILED.  PLEASE REMOVE IT AND REWORK IT!\n\n";
    	    print "Press <enter> to continue\n";
            $input = fgets(STDIN);
        } else {
            // UPdate the database
            print "Updating Database...";
    	    $return = $endpoint->db->AutoExecute($endpoint->device_table, $dev, 'INSERT');
    		if ($return) {
    			print " Done ";					
    		} else {
    			print " Failed ";
            }
            print "\r\n";
    
            print "\n\nThis device Passed.  Please mark it with the serial number ".$dev['DeviceID']."\n\n";
            $SN++;
        }

	}	

	print "Finished\n";
    exit	(0);

function getControllers() {
    global $endpoint;

    $cPkt = array("to" => "FFFFFF", "command" => "DC");
   	$pkt = $endpoint->packet->SendPacket($pInfo, $cPkt);
    $cont = array();
    if (is_array($pkt)) {
    	foreach($pkt as $p) {
    	    $c = $endpoint->InterpConfig(array($p));
        	$cfg = $endpoint->ReadConfig($c);
        
        	$config = $endpoint->InterpConfig($cfg);
            $cont[$p['From']] = $config;
   			if (method_exists($endpoint->drivers[$config['Driver']], "checkProgram")) {
			    print " Checking Program ";
                $ret = $endpoint->drivers[$config['Driver']]->checkProgram($config, $cfg, TRUE);
                if ($ret) {
                    print " Done ";
                } else {
                    print " Failed ";
                }
            }

        }
    } else {
        return FALSE;
    }
    return ($cont);
}


function writeSREC($sn, $pn, $file="/tmp/uispsn") {

    $sn = dechex($sn);
    $sn = str_pad($sn, 10, '0', STR_PAD_LEFT);
    $sn = substr(trim($sn), 0, 10);
    $sn = strtoupper($sn);

    $pn = trim(strtoupper($pn));
    $hexpn = str_replace("-", "", $pn);
    $let = substr($hexpn, strlen($hexpn)-1, 1);
    $hexpn = substr($hexpn, 0, strlen($hexpn)-1);
    $hexpn .= dechex(ord($let));
    
    $hexpn = str_pad(trim($hexpn), 10, '0', STR_PAD_LEFT);
    $hexpn = substr($hexpn, 0, 10);

    $len = 13;
    $hexlen = strtoupper(dechex($len));
    $hexlen = str_pad($hexlen, 2, '0', STR_PAD_LEFT);
    $hexlen = substr(trim($hexlen), 0, 2);

    $string = $hexlen."0000".$sn.$hexpn;
    $csum = 0;
    for ($i = 0; $i < $len; $i++) {
        $csum += hexdec(substr($string, $i*2, 2));
    }
    $csum = (~$csum) & 0xFF;
    $csum = strtoupper(dechex($csum));
    $csum = str_pad($csum, 2, '0', STR_PAD_LEFT);
    $csum = substr(trim($csum), 0, 2);
    $string .= $csum;

    @unlink($file);
	$fp = fopen($file, 'w');
	fwrite($fp, "S0090000736E2E656570AD\r\n");
	fwrite($fp, "S1".$string."\r\n");
	fwrite($fp, "S9030000FC\r\n");
	fclose($fp);

    return $file;

}




?>
