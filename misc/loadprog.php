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
 * @subpackage Test
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007 Hunt Utilities Group, LLC
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$    
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 *
 */

require_once(dirname(__FILE__).'/../head.inc.php');
define("LOADPROG_SVN", '$Id$');

print 'loadprog.php Version '.LOADPROG_SVN."\n";
print "Starting...\n";


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

$endpoint->packet->SNCheck(false);

$dev = $endpoint->getDevice($DeviceID, "ID");

$dev["GatewayIP"] = $GatewayIP;
$dev["GatewayPort"] = $GatewayPort;

$endpoint->packet->verbose = $verbose;
if (is_object($endpoint->drivers[$dev['Driver']]->firmware)) {
    if (empty($program)) {
        $cfg = $endpoint->readConfig($dev);
        $return = $endpoint->drivers[$dev['Driver']]->checkProgram($dev, $cfg, true);        
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
