<?php
/**
 *   <pre>
 *   HUGnetLib is a library of HUGnet code
 *   Copyright (C) 2007 Hunt Utilities Group, LLC
 *   
 *   This program is free software; you can redistribute it and/or
 *   modify it under the terms of the GNU General Public License
 *   as published by the Free Software Foundation; either version 3
 *   of the License, or (at your option) any later version.
 *   
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *   
 *   You should have received a copy of the GNU General Public License
 *   along with this program; if not, write to the Free Software
 *   Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *   </pre>
 *
 *   @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *   @package Scripts
 *   @copyright 2007 Hunt Utilities Group, LLC
 *   @author Scott Price <prices@hugllc.com>
 *   @version $Id$    
 *
 */
define('HUGNET_FS_DIR', dirname(__FILE__));

	require_once(HUGNET_FS_DIR.'/config/config.inc.php');
//	require_once('lib/MDB_QueryWrapper.inc.php');
	require_once('adodb/adodb.inc.php');

//	require_once('lib/functions.inc.php');
	require_once('lib/plugins.inc.php');
	$prefs = &$conf;
	require_once("hugnet.inc.php");
	require_once(HUGNET_INCLUDE_PATH."/process.php");

	require_once('adodb/adodb.inc.php');

    if (is_null($db)) {	
        foreach($prefs['servers'] as $serv) {
    //        $dsn = $serv['Type']."://".$serv["User"].":".rawurlencode($serv["Password"])."@".$serv["Host"]."/".HUGNET_DATABASE;
    //var_dump($dsn);
            $db = &ADONewConnection($serv["Type"]);
            $db->Connect($serv["Host"],$serv["User"],$serv["Password"],HUGNET_DATABASE);
            $dbserver = $serv;
            if ($db->IsConnected()) break;
        }
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
