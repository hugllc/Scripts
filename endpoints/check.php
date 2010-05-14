#!/usr/bin/php-cli
<?php
/**
 * Retrieves the configuration for endpoints
 *
 * PHP Version 5
 *
 * <pre>
 * Scripts related to HUGnet
 * Copyright (C) 2007-2009 Hunt Utilities Group, LLC
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
 * @subpackage Poll
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2009 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */

define("CHECK_PARTNUMBER", "0039-26-07-P");  //0039-26-01-P

require_once dirname(__FILE__).'/../head.inc.php';
require_once HUGNET_INCLUDE_PATH.'/processes/PeriodicCheck.php';

// Set up our configuration
$config = &ConfigContainer::singleton("/etc/hugnet/config.inc.php");
$config->verbose($config->verbose + HUGnetClass::VPRINT_NORMAL);

if ($config->check["enable"] === false) {
    print "Check Disabled...\n";
    die();
}

print "Finding my DeviceID...\n";
$DeviceID = $config->sockets->deviceID(array(), 7);
// This sets us up as a device
print "Setting up my device...\n";
$me = new DeviceContainer(
    array(
        "DeviceID"   => $DeviceID,
        "SerialNum"  => hexdec($DeviceID),
        "DriverInfo" => array(
            "Job" => 7,
            "IP" => PeriodicPlugins::getIP(),
        ),
        "DeviceName" => "Check Process",
        "DeviceLocation" => PeriodicPlugins::getIP(),
        "GatewayKey" => $config->script_gateway,
        "HWPartNum"  => constant("CHECK_PARTNUMBER"),
        "FWPartNum"  => constant("CHECK_PARTNUMBER"),
        "FWVersion"  => constant("SCRIPTS_VERSION"),
    )
);
$me->insertRow(true);


$check = new PeriodicCheck(
    array(
        "PluginDir" => dirname(__FILE__)."/plugins/check",
    ),
    $me
);
$check->powerup();
// Run the main loop
print "Starting... (".$me->DeviceID.")\n";
while ($check->loop === true) {
    $check->main();
    $check->wait();
}

print "Finished\n";

?>
