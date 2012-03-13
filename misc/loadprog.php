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
require_once dirname(__FILE__).'/../../HUGnetLib/src/cli/Daemon.php';
require_once dirname(__FILE__).'/../../HUGnetLib/src/cli/Args.php';
require_once dirname(__FILE__).'/../../HUGnetLib/src/containers/DeviceContainer.php';

print "loadprog.php\n";
print "Starting...\n";

$config = &\HUGnet\cli\Args::factory(
    $argv, $argc,
    array(
        "i" => array("name" => "DeviceID", "type" => "string", "args" => true),
        "D" => array("name" => "Data", "type" => "string", "args" => true),
    )
);
$conf = $config->config();
$conf["network"]["channels"] = 1;
$cli = &\HUGnet\cli\Daemon::factory($conf);

$path = dirname($config->D);
if (empty($path)) {
    $path = ".";
}
$file = basename($config->D);
if (empty($file)) {
    die("Filename must be provided!\n");
}

$DevID = hexdec($config->i);
print "Reprogramming device ".sprintf("%06X", $DevID)."\n";
print "Getting the configuration...  ";
$oldConfig = $cli->system()->device($DevID)->network()->config();
if (!is_object($oldConfig) || is_null($oldConfig->Reply())) {
    die("Failure\n");
}
print "Success!\n";
print "Running the bootloader...  ";
if (!$cli->system()->device($DevID)->network()->runBootloader()) {
    die("Failure\n");
}
print "Success!\n";
print "Getting the bootloader configuration...  ";
$bootConfig = $cli->system()->device($DevID)->network()->config();
if (!is_object($bootConfig) || is_null($bootConfig->Reply())) {
    die("Failure\n");
}
print "Success!\n";


$firmware = new FirmwareTable();
$firmware->fromFile($file, $path);
print "Found firmware ".$firmware->FWPartNum." v".$firmware->Version."\n";

print "Writing the code...\n";
$code = $cli->system()->device($DevID)->network()->writeFlashBuffer(
    $firmware->getCode()
);
if (!$code) {
    die("Failed to write code\n");
}
print "Writing the data...\n";
$data = $cli->system()->device($DevID)->network()->writeE2Buffer(
    $firmware->getData(), 10
);
if (!$data) {
    die("Failed to write data\n");
}

print "Setting the CRC...  ";
$crc = $cli->system()->device($DevID)->network()->setCRC();
if ($crc === false) {
    die("Failure\n");
}
print $crc."\n";

print "Running the application...  ";
if (!$cli->system()->device($DevID)->network()->runApplication()) {
    die("Failure\n");
}
print "Success!\n";

print "Finished\n";
?>
