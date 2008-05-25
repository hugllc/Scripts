<?php
/**
 *
 * PHP Version 5
 *
 * <pre>
 * HUGnetLib is a library of HUGnet code
 * Copyright (C) 2007 Hunt Utilities Group, LLC
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 3
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 * </pre>
 *
 * @category   Scripts
 * @package    Scripts
 * @subpackage Includes
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$    
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
define('HUGNET_FS_DIR', dirname(__FILE__));
define('SCRIPTS_VERSION', "0.5.0");
define("SCRIPTS_PARTNUMBER", "0039-26-00-P");  //0039-26-00-P

if (!@include_once '/etc/hugnet/config.inc.php') {
    if (!@include_once HUGNET_FS_DIR.'/config/config.inc.php') {
        echo "No config file found.  \n";
        echo "Checked /etc/hugnet/config.inc.php, ".HUGNET_FS_DIR."/config/config.inc.php\n";
        echo "Waiting 60 seconds then dying.\n";
        sleep(60);
        die();
    }
}

if (file_exists("/home/hugnet/HUGnetLib/hugnet.inc.php")) {
    include_once "/home/hugnet/HUGnetLib/hugnet.inc.php";
} else {
    include_once "hugnet.inc.php";
}

require_once HUGNET_INCLUDE_PATH.'/lib/plugins.inc.php';

$GatewayKey = $hugnet_config["script_gatewaykey"];

if (!isset($GatewayIP)) $GatewayIP = (empty($hugnet_config["gatewayIP"])) ? "127.0.0.1" : $hugnet_config["gatewayIP"];
if (!isset($GatewayPort)) $GatewayPort = (empty($hugnet_config["gatewayPort"])) ? "2000" : $hugnet_config["gatewayPort"];

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
        $hugnet_config["test"] = true;
        print "Test Mode Enabled\n";
        break;

    // Packet Serial Number to use
    case "-v":
        $hugnet_config["verbose"]++;
        print "Verbose Mode Enabled\n";
        break;

    // Go into an array that can be sorted by the program
    default:
        $newArgv[] = $argv[$i];
        break;
    }
}
if ($phpunit) {
    print "PHPUnit installed and ready.\n";
}
print 'HUGnet Scripts Version '.SCRIPTS_VERSION."\n";
print 'HUGnet Lib Version '.HUGNET_LIB_VERSION."\n";

?>
