<?php
/**
	$Id$
	@file head.inc.php
	
	
	$Log: head.inc.php,v $
	Revision 1.7  2006/03/10 14:55:01  prices
	Fixed the analysis.
	
	Revision 1.6  2006/02/27 17:50:41  prices
	Periodic checkin
	
	Revision 1.5  2006/02/14 15:49:54  prices
	Periodic commit.
	
	Revision 1.4  2006/02/02 14:56:09  prices
	Periodic checkin
	
	Revision 1.3  2005/10/18 20:13:46  prices
	Periodic
	
	Revision 1.2  2005/06/03 17:12:55  prices
	More stuff is being offloaded from the driver class into the specific or eDEFAULT drivers.
	This makes more sense.
	
	Revision 1.1  2005/06/01 20:45:04  prices
	Header file for all scripts.
	
	Revision 1.1  2005/05/24 21:48:52  prices
	Inception
	

*/	
define('HUGNET_FS_DIR', dirname(__FILE__));

	require_once(HUGNET_FS_DIR.'/config/config.inc.php');
//	require_once('lib/MDB_QueryWrapper.inc.php');
	require_once('adodb/adodb.inc.php');

	require_once('lib/functions.inc.php');
	require_once('lib/plugins.inc.php');
	$prefs = &$conf;
	require_once("hugnet.inc.php");
	require_once(HUGNET_INCLUDE_PATH."/process.inc.php");

	require_once('adodb/adodb.inc.php');
	
    foreach($prefs['servers'] as $serv) {
//        $dsn = $serv['Type']."://".$serv["User"].":".rawurlencode($serv["Password"])."@".$serv["Host"]."/".HUGNET_DATABASE;
//var_dump($dsn);
        $db = &ADONewConnection($serv["Type"]);
        $db->Connect($serv["Host"],$serv["User"],$serv["Password"],HUGNET_DATABASE);
        if ($db->IsConnected()) break;
    }
    
    if (!$db->IsConnected()) die("Database Connection Failed\n");

    if (!isset($GatewayIP)) $GatewayIP = "127.0.0.1";
    if (!isset($GatewayPort)) $GatewayPort = 2000;
    $newArgv = array();
    for ($i = 1; $i < count($argv); $i++) {
        switch($argv[$i]) {
            // Gateway IP address
            case "-a":
                $i++;
                $GatewayIP = $argv[$i];
                break;
            // Packet Command
            case "-c":
                $i++;
                $pktCommand = $argv[$i];
                break;
            // Packet Data
            case "-d":
                $i++;
                $pktData = $argv[$i];
                break;
            // Gateway Key
            case "-g":
                $i++;
                $GatewayKey = $argv[$i];
                break;
            // DeviceID
            case "-i":
                $i++;
                $DeviceID = $argv[$i];
                break;
                
            // DeviceKey
            case "-k":
                $i++;
                $DeviceKey = $argv[$i];
                break;
            // Gateway Port
            case "-p":
                $i++;
                $GatewayPort = $argv[$i];
                break;
                
            // Packet Serial Number to use
            case "-s":
                $i++;
                $SerialNum = $argv[$i];
                break;
            // Packet Serial Number to use
            case "-t":
                $testMode = TRUE;
        		print "Test Mode Enabled\n";
                break;

            // Packet Serial Number to use
            case "-v":
                $verbose++;
        		print "Verbose Mode Enabled\n";
                break;

            // Go into an array that can be sorted by the program
            default:
                $newArgv[] = $argv[$i];
                break;
        }
    }

    $endpoint = new driver($db, $conf['hugnetDb']);
    $endpoint->packet->verbose = $verbose;
?>
