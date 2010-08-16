<?php
/**
 * Loads a program into a controller board
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
 * @subpackage Test
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2009 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 *
 */

/** HUGnet code */
require_once dirname(__FILE__).'/../head.inc.php';
/** Packet log include stuff */
require_once HUGNET_INCLUDE_PATH.'/containers/PacketContainer.php';
require_once HUGNET_INCLUDE_PATH.'/containers/DeviceContainer.php';

print "loadprog.php\n";
print "Starting...\n";

print "Using GatewayKey ".$GatewayKey.", DeviceKey ".$DeviceKey."\n";

$config = &ConfigContainer::singleton("/etc/hugnet/config.inc.php");
$config->verbose($hugnet_config["verbose"] + HUGnetClass::VPRINT_NORMAL);
$dev = new DeviceContainer();
$dev->getRow(hexdec($DeviceID));
$dev->runBootloader();
$dev->readConfig();
var_dump($dev->writeProgram($pktData));
$dev->readConfig();

print "Finished\n";
?>
