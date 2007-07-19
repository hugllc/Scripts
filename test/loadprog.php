<?php
/**
	$Id$
	@file scripts/test/loadprog.php
	@brief Loads a program into a 0039-21 controller board remotely.
	
	$Log: loadprog.php,v $
	Revision 1.1  2005/06/01 15:03:28  prices
	Moving from the 08/scripts directory.  This is the new home.
	
	Revision 1.7  2005/05/10 13:39:31  prices
	Periodic Checkin
	
	Revision 1.6  2005/04/12 13:57:08  prices
	The polling now accounts for the fact that controller boards might lose the location of endpoints.
	
	Revision 1.5  2005/04/12 02:00:44  prices
	Removed the locking to see if it still worked.  It still seems to.
	
	Revision 1.4  2005/04/05 13:37:27  prices
	Added lots of documentation.
	
	Revision 1.3  2005/03/25 20:19:59  prices
	It now loads a program correctly.
	
	Revision 1.2  2005/03/19 02:17:00  prices
	Fixed a bug that didn't check to see if we were running the bootloader before it went on.
	
	Revision 1.1  2005/03/19 00:35:07  prices
	Loads a program into a board.
	
	Revision 1.2  2005/02/18 16:48:54  prices
	Many changes to accomodate the new controller boards, plus to move from hugnetd to seriald.
	
	Revision 1.1  2005/02/17 02:24:49  prices
	Periodic Checkin
	
*/
/**
 * @cond	SCRIPT
*/

 	require_once(dirname(__FILE__).'/../head.inc.php');
    for ($i = 0; $i < count($newArgv); $i++) {
        switch($newArgv[$i]) {
            // Gateway IP address
            case "-P":
                $i++;
                $program = $newArgv[$i];
                break;
            // Gateway IP address
            case "-V":
                $i++;
                $version = $newArgv[$i];
                break;
        }
    }

    $endpoint->packet->SNCheck(FALSE);

	$dev = $endpoint->getDevice($DeviceID, "ID");

	$dev["GatewayIP"] = $GatewayIP;
	$dev["GatewayPort"] = $GatewayPort;

	$endpoint->packet->verbose = $verbose;
	if (is_object($endpoint->drivers[$dev['Driver']]->firmware)) {
        if (empty($program)) {
            $cfg = $endpoint->ReadConfig($dev);
            $return = $endpoint->drivers[$dev['Driver']]->checkProgram($dev, $cfg, TRUE);        
        } else {
    
            $res = $endpoint->drivers[$dev['Driver']]->firmware->getFirmware($program, $version);
            if (is_array($res)) {
                print " found v".$res[0]['FirmwareVersion']."\r\n";

                $return = $endpoint->drivers[$dev['Driver']]->RunBootloader($dev);
                $return = $endpoint->drivers[$dev['Driver']]->loadProgram($dev, $dev, $res[0]['FirmwareKey']);
            }
        }
    } else {
        print "Not a loadable device\r\n";
    }
?>
