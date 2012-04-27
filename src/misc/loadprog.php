#!/usr/bin/env php
<?php
/**
 * Loads a program into a controller board
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
 * @subpackage Test
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2011 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 *
 */
require_once 'HUGnetLib/ui/Daemon.php';
require_once 'HUGnetLib/ui/Args.php';
require_once 'HUGnetLib/containers/DeviceContainer.php';

print "loadprog.php\n";
print "Starting...\n";

$config = &\HUGnet\ui\Args::factory(
    $argv, $argc,
    array(
        "i" => array("name" => "DeviceID", "type" => "string", "args" => true),
        "D" => array("name" => "Data", "type" => "string", "args" => true),
    )
);
$conf = $config->config();
$conf["network"]["channels"] = 1;
$cli = &\HUGnet\ui\Daemon::factory($conf);

$path = dirname($config->D);
if (empty($path)) {
    $path = ".";
}
$file = basename($config->D);
if (empty($file)) {
    die("Filename must be provided!\n");
}

$DevID = hexdec($config->i);
$dev = $cli->system()->device($DevID);
$firmware = new FirmwareTable();
$firmware->verbose(10);
$firmware->fromFile($file, $path);
print "Found firmware ".$firmware->FWPartNum." v".$firmware->Version."\n";

$ret = $dev->network()->loadFirmware($firmware);
if (!$ret) {
    print "Failure!!!\n";
    exit(1);
}
print "Finished\n";
?>
