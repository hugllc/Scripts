<?php
/**
	$Id: programEP.php 337 2006-11-16 18:18:23Z prices $
	@file scripts/misc/programEP.php
	@brief Sets up and runs UISP to program a device.
	
	$Log: programEP.php,v $
	Revision 1.3  2006/02/14 15:49:54  prices
	Periodic commit.
	
	Revision 1.2  2005/06/01 20:44:52  prices
	Updated them to work with the new setup.
	
	Revision 1.1  2005/06/01 18:30:34  prices
	Inception
	
	Revision 1.1  2005/05/10 20:20:10  prices
	Programs an endpoint and caches the data so that it can program others without a network connection.
	
	
*/
/**
 * @cond SCRIPT
 */
	require_once(dirname(__FILE__).'/../head.inc.php');

	require_once('firmware.inc.php');
	
	if (empty($argv[1])) {
		die("Usage: ".$argv[0]." <firmwarePart> [ <clean> [ <parallel port> ] ]\n"); 
	}
	if (empty($argv[3])) {
		$pPort = '/dev/parport0';
	} else {
		$pPort = $argv[3];
	}

	if (trim(strtolower($argv[1])) == 'clean') {
		unlink('/tmp/uisp*');
		die();	
	}


	if ((trim(strtolower($argv[2])) != 'clean') && file_exists("/tmp/uisp".$argv[1])) {
		print "Reading information from cache: ";
		$ret = file("/tmp/uisp".$argv[1]);
		$ret = implode("", $ret);
		$ret = trim($ret);
		$ret = unserialize($ret);
		print "  Found Version: ".$ret['FirmwareVersion']."\n";
	}
	if (!isset($ret['FirmwareCode'])) {
		print "Reading information from database: ";
		$dsn = "mysql://Portal:".urlencode("Por*tal")."@floyd.int.hugllc.com/HUGNet";
		$db = NewADOConnection($dsn);
		$firmware = new firmware($db);
		$ret = $firmware->GetLatestFirmware($argv[1]);

		print "  Found Version: ".$ret['FirmwareVersion']."\n";

		print "Caching...";
		@unlink("/tmp/uisp".$ret["FWPartNum"]);
		$fp = fopen("/tmp/uisp".$ret["FWPartNum"], 'w');
		fwrite($fp, serialize($ret));
		fclose($fp);
		print "Done\n";
	}

    if (!is_null($ret)) {
    //	$Prog = 'uisp -dprog=dapa -v -dpart='.$ret['Target'].' -dlpt='.$pPort.' -dvoltage=5.0';
        $Prog = 'avrdude -c avrisp2 -p '.$ret['Target'].' -P usb ';
    
    	// Program the flash
    	$tempname = tempnam("/tmp", "uisp");
    	
    	$fp = fopen($tempname, "w");
    	fwrite($fp, $ret['FirmwareCode']);
    	fclose($fp);
    //	$flash = ' --segment=flash --erase --upload --verify if='.$tempname;
        $flash = ' -e -U flash:w:'.$tempname.':s';
    	print "Using: ".$Prog.$flash."\n";
    	passthru($Prog.$flash);
    	unlink($tempname);
    
    	// Program the E2
    	$tempname = tempnam("/tmp", "uisp");
    	
    	$fp = fopen($tempname, "w");
    	fwrite($fp, $ret['FirmwareData']);
    	fclose($fp);
//    	$eeprom = ' --segment=eeprom --upload --verify if='.$tempname;
        $eeprom = ' -V -U eeprom:w:'.$tempname.':s';
    
    	print "Using: ".$Prog.$eeprom."\n";
    	passthru($Prog.$eeprom);
    
    	unlink($tempname);
    } else {
        print "Firmware not found. \n";
    }

/**
 * @endcond
 */
?>
