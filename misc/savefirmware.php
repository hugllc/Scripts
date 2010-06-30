<?php
/**
 * Saves firmware to the database
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
 * @subpackage Misc
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
require_once HUGNET_INCLUDE_PATH.'/tables/FirmwareTable.php';

print "monitor.php\n";
print "Starting...\n";

// Set up our configuration
$config = &ConfigContainer::singleton("/etc/hugnet/config.inc.php");
$config->verbose($config->verbose + HUGnetClass::VPRINT_NORMAL);


$Code = implode("", file($argv[2]));
$Data = implode("", file($argv[3]));
// This sets us up as a device
$firmware = new FirmwareTable(array(
    "group"     => "firmware",
    "Version"   => $argv[1],
    "Code"      => $Code,
    "CodeHash"  => md5($Code),
    "Data"      => $Data,
    "DataHash"  => md5($Data),
    "FWPartNum" => $argv[5],
    "HWPartNum" => $argv[6],
    "Date"      => time(),
    "FileType"  => $argv[4],
    "RelStatus" => $argv[7],
    "Tag"       => $argv[8],
    "Target"    => $argv[9],
));

var_dump($firmware->toArray());

$firmware->toFile(".");

?>
