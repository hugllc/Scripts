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

define("CONFIG_PARTNUMBER", "0039-26-06-P");  //0039-26-01-P

require_once dirname(__FILE__).'/../head.inc.php';
require_once HUGNET_INCLUDE_PATH.'/processes/DeviceConfig.php';

// Set up our configuration
$config = &ConfigContainer::singleton("/etc/hugnet/config.inc.php");
$config->verbose($config->verbose + HUGnetClass::VPRINT_NORMAL);

// Check to make sure this is enabled
if ($config->config["enable"] === false) {
    print "Config Disabled...\n";
    die();
}

print "Finding my DeviceID...\n";
$DeviceID = $config->sockets->deviceID(array(), 6);
// This sets us up as a device
print "Setting up my device...\n";
$me = array(
    "id"         => hexdec($DeviceID),
    "DeviceID"   => $DeviceID,
    "DriverInfo" => array(
        "Job" => 6,
        "IP" => DeviceConfig::getIP(),
    ),
    "DeviceName" => "Config Process",
    "HWPartNum"  => constant("CONFIG_PARTNUMBER"),
    "FWPartNum"  => constant("CONFIG_PARTNUMBER"),
    "FWVersion"  => constant("SCRIPTS_VERSION"),
);

$devConfig = new DeviceConfig(array(), $me);
$devConfig->powerup();
// Get the configuration of the devices with loadable firmware
// This is done twice incase they need to be reloaded with
// a new version of firmware.  If all is fine this will add
// very little startup time.
print "Checking loadable devices\n";
$devConfig->config(true);
$devConfig->config(true);

// Run the main loop
print "Starting... (".$DeviceID.")\n";
while ($devConfig->loop) {
    $devConfig->config();
    $devConfig->wait();
}

print "Finished\n";

?>
