<?php
/**
 * Programs an endpoint using firmware from the database
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
require_once dirname(__FILE__).'/../head.inc.php';

print "Starting...\n";

require_once HUGNET_INCLUDE_PATH.'/tables/FirmwareTable.php';
require_once HUGNET_INCLUDE_PATH.'/containers/ConfigContainer.php';
$config = &ConfigContainer::singleton("/etc/hugnet/config.inc.php");

if (empty($argv[1])) {
    die("Usage: ".$argv[0]." <firmwarePart> <hardwarePart> [ <clean> ]\n");
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
    $flash = ' -e -U flash:w:'.$tempname.':s';
    print "Using: ".$Prog.$flash."\n";
    passthru($Prog.$flash);
    unlink($tempname);

    // Program the E2
    $tempname = tempnam("/tmp", "uisp");

    $fp = fopen($tempname, "w");
    fwrite($fp, $firmware->Data);
    fclose($fp);
    //        $eeprom = ' --segment=eeprom --upload --verify if='.$tempname;
    $eeprom = ' -V -U eeprom:w:'.$tempname.':s';

    print "Using: ".$Prog.$eeprom."\n";
    passthru($Prog.$eeprom);

    unlink($tempname);
} else {
    print "Firmware not found. \n";
}

/**
 * @endcond
 */
?>
