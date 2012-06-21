<?php
/**
 * Programs an endpoint using firmware from the database
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
 * @subpackage Misc
 * @author     Scott Price <prices@hugllc.com>
 * @copyright  2007-2011 Hunt Utilities Group, LLC
 * @copyright  2009 Scott Price
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    SVN: $Id$
 * @link       https://dev.hugllc.com/index.php/Project:Scripts
 *
 */
require_once dirname(__FILE__).'/../head.inc.php';

print "Starting...\n";

require_once HUGNET_INCLUDE_PATH.'/tables/FirmwareTable.php';
require_once HUGNET_INCLUDE_PATH.'/containers/ConfigContainer.php';
require_once 'HUGnetLib/ui/CLI.php';
require_once 'HUGnetLib/ui/Args.php';

$config = &ConfigContainer::singleton("/etc/hugnet/config.inc.php");

$config = &\HUGnet\ui\Args::factory(
    $argv, $argc,
    array(
        "i" => array("name" => "DeviceID", "type" => "string", "args" => true),
        "D" => array("name" => "Data", "type" => "string", "args" => true),
    )
);
$config->addLocation("/usr/share/HUGnet/config.ini");
$conf = $config->config();
$conf["network"]["channels"] = 1;
$cli = &\HUGnet\ui\CLI::factory($conf);
$cli->help(
    $cli->system()->get("program")."
Copyright Hunt Utilities Group, LLC

A utility to load programs into devices

Usage: ".$cli->system()->get("program")." [-v] [-f <file>] -i <DeviceID> -D <file>
Arguments:
    -i <DeviceID>   The device ID to load.  Should be a hex value up to 6 digits
    -D <file>       The file to load into the device
    -v              Increment the verbosity
    -f <file>       The config file to use",
    $config->h
);
if (strlen($config->i) == 0) {
    $cli->help();
    $cli->out();
    $cli->out("DeviceID must be specified");
    exit(1);
}

if (trim(strtolower($argv[2])) == 'clean') {
    unlink('/tmp/uisp*');
    die();
}


print "Reading information from database: ";

$firmware = new FirmwareTable();
$firmware->fromArray(
    array(
        "FWPartNum" => $argv[1],
    )
);
$firmware->getLatest();

print "  Found Version: ".$firmware->Version."\n";


if (!empty($firmware->id)) {
    $Prog = 'avrdude -c avrisp2 -p '.$firmware->Target.' -P usb ';

    // Program the flash
    $tempname = tempnam("/tmp", "uisp");

    $fp = fopen($tempname, "w");
    fwrite($fp, $firmware->Code);
    fclose($fp);
    //    $flash = ' --segment=flash --erase --upload --verify if='.$tempname;
    $flash = ' -e -U flash:w:'.$tempname;
    print "Using: ".$Prog.$flash."\n";
    passthru($Prog.$flash);
    unlink($tempname);

    // Program the E2
    $tempname = tempnam("/tmp", "uisp");

    $fp = fopen($tempname, "w");
    fwrite($fp, $firmware->Data);
    fclose($fp);
    //        $eeprom = ' --segment=eeprom --upload --verify if='.$tempname;
    $eeprom = ' -V -U eeprom:w:'.$tempname;

    print "Using: ".$Prog.$eeprom."\n";
    passthru($Prog.$eeprom);

    unlink($tempname);

    if (!empty($pktData)) {
        // Program the user data
        $tempname = tempnam("/tmp", "uisp");
        writeSREC($pktData, $tempname);

        $eeprom = ' -V -U eeprom:w:'.$tempname;

        print "Using: ".$Prog.$eeprom."\n";
        passthru($Prog.$eeprom);

        unlink($tempname);
    }

} else {
    print "Firmware not found. \n";
}

/**
 * @endcond
 */
/**
* This writes the srecord
*
* @param string $data The data to write
* @param string $file The file name to use
*
* @return string the file name used
*/
function writeSREC($data, $file)
{


    $len = strlen($data)/2 + 3;
    $hexlen = strtoupper(dechex($len));
    $hexlen = str_pad($hexlen, 2, '0', STR_PAD_LEFT);
    $hexlen = substr(trim($hexlen), 0, 2);

    $string = $hexlen."0000".$data;
    $csum = 0;
    for ($i = 0; $i < $len; $i++) {
        $csum += hexdec(substr($string, $i*2, 2));
    }
    $csum = (~$csum) & 0xFF;
    $csum = strtoupper(dechex($csum));
    $csum = str_pad($csum, 2, '0', STR_PAD_LEFT);
    $csum = substr(trim($csum), 0, 2);
    $string .= $csum;

    @unlink($file);
    $fp = fopen($file, 'w');
    fwrite($fp, "S0090000736E2E656570AD\r\n");
    fwrite($fp, "S1".$string."\r\n");
    fwrite($fp, "S9030000FC\r\n");
    fclose($fp);

    return $file;

}

?>
