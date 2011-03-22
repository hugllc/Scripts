#!/usr/bin/php-cli
<?php
/**
 * Retrieves the configuration for endpoints
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
 * @subpackage Poll
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2011 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 */

define("ANALYSIS_PARTNUMBER", "0039-26-03-P");  //0039-26-01-P

require_once dirname(__FILE__).'/../head.inc.php';
require_once HUGNET_INCLUDE_PATH.'/processes/DeviceAnalysis.php';
require_once HUGNET_INCLUDE_PATH."/base/DeviceProcessPluginBase.php";

// Set up our configuration
$config = &ConfigContainer::singleton($config_file);
$config->verbose($config->verbose + HUGnetClass::VPRINT_NORMAL);

// This sets us up as a device
$me = array(
    "id" => hexdec($DeviceID),
    "DeviceID" => $DeviceID,
    "DriverInfo" => array(
        "Job" => 3,
        "IP" => DeviceAnalysis::getIP(),
    ),
    "DeviceName" => "Analysis Process",
    "HWPartNum"  => constant("ANALYSIS_PARTNUMBER"),
    "FWPartNum"  => constant("ANALYSIS_PARTNUMBER"),
    "FWVersion"  => constant("SCRIPTS_VERSION"),
);

$analysis = new DeviceAnalysis(
    array(
        "PluginDir" => dirname(__FILE__)."/plugins/analysis",
        "PluginType" => "analysis",
    ),
    $me
);
$analysis->powerup();
// Run the main loop
while ($analysis->loop === true) {
    $analysis->main();
    $analysis->wait();
}

print "Finished\n";

?>
