<?php
/**
 * This file does the meat of the command line stuff.  It gets any script that
 * is called from the command line ready to go.
 *
 * PHP Version 5
 *
 * <pre>
 * Scripts related to HUGnet
 * Copyright (C) 2007-2011 Hunt Utilities Group, LLC
 * Copyright (C) 2009 Scott Price
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
 * @copyright  2007-2011 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */
define('HUGNET_FS_DIR', dirname(__FILE__));
//define('SCRIPTS_VERSION', "0.6.1");
define("SCRIPTS_VERSION", trim(file_get_contents(HUGNET_FS_DIR."/VERSION.TXT")));
define("SCRIPTS_PARTNUMBER", "0039-26-00-P");  //0039-26-00-P

// This forces the timezone to UTC
putenv("TZ=UTC");

if (!@include_once '/etc/hugnet/config.inc.php') {
    if (!@include_once HUGNET_FS_DIR.'/config/config.inc.php') {
        echo "No config file found.  \n";
        echo "Checked /etc/hugnet/config.inc.php, ";
        echo HUGNET_FS_DIR."/config/config.inc.php\n";
        echo "Waiting 60 seconds then dying.\n";
        sleep(60);
        die();
    }
}

if (file_exists("/home/hugnet/HUGnetLib/hugnet.inc.php")) {
    include_once "/home/hugnet/HUGnetLib/hugnet.inc.php";
} else {

    if (!@include_once $hugnet_config["HUGnetLib_dir"]."/hugnet.inc.php") {
        if (!@include_once dirname(__FILE__)."/../HUGnetLib/hugnet.inc.php") {
            if (!@include_once "HUGnetLib/hugnet.inc.php") {
                include_once "hugnet.inc.php";
            }
        }
    }
}

$GatewayKey = $hugnet_config["script_gatewaykey"];
$group = "default";
$config_file = "/etc/hugnet/config.inc.php";
$hugnet_config["loop"] = 1;
$newArgv = array();
for ($i = 1; $i < count($argv); $i++) {
    switch($argv[$i]) {
    // Gateway IP address
    case "-a":
        $i++;
        $GatewayIP                  = $argv[$i];
        $hugnet_config["GatewayIP"] = $argv[$i];
        break;
    // Packet Command
    case "-c":
        $i++;
        $pktCommand                  = $argv[$i];
        $hugnet_config["pktCommand"] = $argv[$i];
        break;
    // Packet Data
    case "-d":
        $i++;
        $pktData                  = $argv[$i];
        $hugnet_config["pktData"] = $argv[$i];
        break;
    // Packet Data
    case "-db":
        $i++;
        $group                  = $argv[$i];
        $hugnet_config["group"] = $argv[$i];
        break;
        // Gateway Key
    case "-f":
        $i++;
        $config_file = $argv[$i];
        break;
        // Gateway Key
    case "-g":
        $i++;
        $GatewayKey                         = $argv[$i];
        $hugnet_config["script_gatewaykey"] = $argv[$i];
        break;
    // DeviceID
    case "-i":
        $i++;
        $DeviceID                  = $argv[$i];
        $hugnet_config["DeviceID"] = $argv[$i];
        break;

    // DeviceKey
    case "-k":
        $i++;
        $DeviceKey                  = $argv[$i];
        $hugnet_config["DeviceKey"] = $argv[$i];
        break;
    // Packet Serial Number to use
    case "-n":
        $i++;
        $Count                  = $argv[$i];
        print "Test Mode Enabled\n";
        break;
    // Gateway Port
    case "-p":
        $i++;
        $GatewayPort                  = $argv[$i];
        $hugnet_config["GatewayPort"] = $argv[$i];
        break;
    // Packet Serial Number to use
    case "-s":
        $i++;
        $SerialNum                  = $argv[$i];
        $hugnet_config["SerialNum"] = $argv[$i];
        break;
    // Packet Serial Number to use
    case "-t":
        $hugnet_config["test"] = true;
        print "Test Mode Enabled\n";
        break;

    // Packet Serial Number to use
    case "-v":
        $hugnet_config["verbose"]++;
        print "Verbose Mode Set To ".$hugnet_config["verbose"]."\n";
        break;

    // Packet Serial Number to use
    case "-1":
        $hugnet_config["loop"] = 0;
        print "One Shot mode enabled\n";
        break;

    // Go into an array that can be sorted by the program
    default:
        $newArgv[] = $argv[$i];
        $newArgc++;
        break;
    }
}
if ($phpunit) {
    print "PHPUnit installed and ready.\n";
}
if (!$silent) {
    print "HUGnet Scripts Version ".SCRIPTS_VERSION."\n";
    print "HUGnet Lib Version ".HUGNET_LIB_VERSION."\n";
    print "All script times are in UTC\n";
}
?>
